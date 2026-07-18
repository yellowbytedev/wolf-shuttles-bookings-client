#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const notes = [];
const activeFiles = new Set();
const instructionLimit = 32 * 1024;

function addIfFile(relative) {
  const absolute = path.join(root, relative);
  if (fs.existsSync(absolute) && fs.statSync(absolute).isFile()) activeFiles.add(absolute);
}

function walk(directory, predicate) {
  if (!fs.existsSync(directory)) return;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    if (['.git', 'node_modules', 'vendor', '.kilo'].includes(entry.name)) continue;
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(absolute, predicate);
    else if (predicate(absolute)) activeFiles.add(absolute);
  }
}

addIfFile('AGENTS.md');
addIfFile('AGENTS.override.md');
addIfFile('docs/booking-v4/CURRENT-IMPLEMENTATION-STATE.md');
walk(path.join(root, 'docs', 'codex'), file => file.endsWith('.md'));
walk(path.join(root, '.agents', 'skills'), file =>
  file.endsWith('SKILL.md') || file.endsWith(path.join('agents', 'openai.yaml'))
);

const agentFile = path.join(root, 'AGENTS.md');
const overrideFile = path.join(root, 'AGENTS.override.md');
if (!fs.existsSync(agentFile)) {
  failures.push('missing root AGENTS.md');
} else if (fs.statSync(agentFile).size > instructionLimit) {
  failures.push('root AGENTS.md exceeds the 32 KiB instruction limit');
}
if (fs.existsSync(overrideFile)) {
  failures.push('unexpected active AGENTS.override.md overrides root guidance');
}

function yamlValue(source, key) {
  const block = source.match(/^interface:\s*\n((?:[ \t]+.*(?:\n|$))*)/m)?.[1] ?? '';
  const value = block.match(new RegExp(`^\\s{2}${key}:\\s*(.+)$`, 'm'))?.[1]?.trim() ?? '';
  return value.replace(/^(?:"([\s\S]*)"|'([\s\S]*)')$/, '$1$2').trim();
}

const skillRoot = path.join(root, '.agents', 'skills');
const skillNames = new Map();
let yamlCount = 0;
if (fs.existsSync(skillRoot)) {
  for (const folder of fs.readdirSync(skillRoot).sort()) {
    const skillFile = path.join(skillRoot, folder, 'SKILL.md');
    if (!fs.existsSync(skillFile)) continue;
    const source = fs.readFileSync(skillFile, 'utf8');
    const frontmatter = source.match(/^---\n([\s\S]*?)\n---/);
    if (!frontmatter) {
      failures.push(`${path.relative(root, skillFile)} has no YAML frontmatter`);
      continue;
    }
    const keys = [...frontmatter[1].matchAll(/^([a-z_]+):/gm)].map(match => match[1]);
    const unexpected = keys.filter(key => !['name', 'description'].includes(key));
    if (unexpected.length) failures.push(`${path.relative(root, skillFile)} has unexpected frontmatter keys: ${unexpected.join(', ')}`);
    const name = frontmatter[1].match(/^name:\s*(.+)$/m)?.[1].trim();
    const description = frontmatter[1].match(/^description:\s*(.+)$/m)?.[1].trim();
    if (!name || name !== folder) failures.push(`${path.relative(root, skillFile)} name must match folder`);
    if (!description) failures.push(`${path.relative(root, skillFile)} needs a non-empty description`);
    if (name) {
      if (skillNames.has(name)) failures.push(`duplicate skill name ${name}`);
      skillNames.set(name, skillFile);
    }

    const yaml = path.join(skillRoot, folder, 'agents', 'openai.yaml');
    if (!fs.existsSync(yaml)) {
      failures.push(`${path.relative(root, skillFile)} is missing agents/openai.yaml`);
      continue;
    }
    yamlCount += 1;
    activeFiles.add(yaml);
    const yamlSource = fs.readFileSync(yaml, 'utf8');
    if (!/^interface:\s*$/m.test(yamlSource)) failures.push(`${path.relative(root, yaml)} is missing an interface mapping`);
    for (const key of ['display_name', 'short_description', 'default_prompt']) {
      if (!yamlValue(yamlSource, key)) failures.push(`${path.relative(root, yaml)} has an empty interface.${key}`);
    }
  }
}

const expectedBookingReference = '../../../../../../../../docs/';
function checkBookingReference(readme, logicalDirectory) {
  if (!fs.existsSync(readme)) return;
  const source = fs.readFileSync(readme, 'utf8');
  if (!source.includes(`\`${expectedBookingReference}\``)) {
    failures.push(`${path.relative(root, readme)} has an incorrect parent docs reference`);
    return;
  }
  const resolved = path.resolve(logicalDirectory, expectedBookingReference);
  const expected = logicalDirectory.startsWith(root)
    ? path.join(root, 'docs')
    : path.resolve('/workspace/docs');
  if (resolved !== expected) failures.push(`${path.relative(root, readme)} parent docs reference resolves incorrectly`);
}

const localBookingReadme = path.join(root, 'docs', 'codex', 'README.md');
if (path.basename(root) === 'ws-bookings') {
  checkBookingReference(localBookingReadme, '/workspace/booking-site/app/public/wp-content/plugins/ws-bookings/docs/codex');
}
const workspaceBookingReadme = path.join(root, 'booking-site/app/public/wp-content/plugins/ws-bookings/docs/codex/README.md');
if (fs.existsSync(workspaceBookingReadme)) {
  checkBookingReference(workspaceBookingReadme, path.dirname(workspaceBookingReadme));
}

const forbiddenOrigins = [
  /http:\/\/(?:www\.)?wolfshuttles\.local/i,
  /http:\/\/bookings\.wolfshuttles\.local/i,
  /\blocalhost\b/i
];
const secretPatterns = [
  /sk-(?:proj-)?[A-Za-z0-9_-]{20,}/,
  /AKIA[0-9A-Z]{16}/,
  /AIza[0-9A-Za-z_-]{35}/,
  /gh[pousr]_[A-Za-z0-9]{36,}/,
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /Authorization:\s*Bearer\s+[A-Za-z0-9._-]{16,}/i,
  /(?:api[_-]?key|client[_-]?secret|password)\s*[:=]\s*["'][^"']{16,}["']/i
];

for (const file of activeFiles) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file);
  if (/\bTODO\b/.test(source)) failures.push(`${relative} contains a TODO placeholder`);
  for (const pattern of forbiddenOrigins) {
    if (pattern.test(source)) failures.push(`${relative} contains forbidden active origin guidance`);
  }
  for (const pattern of secretPatterns) {
    if (pattern.test(source)) failures.push(`${relative} resembles committed secret material`);
  }
  if (!file.endsWith('.md')) continue;
  for (const match of source.matchAll(/\[[^\]]*\]\(([^)]+)\)/g)) {
    const target = match[1].trim().split('#')[0].replace(/^<|>$/g, '');
    if (!target || /^(?:https?:|mailto:|#)/.test(target) || target.startsWith('/')) continue;
    if (!fs.existsSync(path.resolve(path.dirname(file), target))) failures.push(`${relative} has unresolved link: ${target}`);
  }
}

notes.push(`${activeFiles.size} active guidance and metadata files checked`);
notes.push(`${skillNames.size} repo-local skills checked`);
notes.push(`${yamlCount} skill metadata files checked`);
if (fs.existsSync(agentFile)) notes.push(`instruction size ${fs.statSync(agentFile).size}/${instructionLimit} bytes`);

if (failures.length) {
  console.error('Codex operating-layer audit: FAIL');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Codex operating-layer audit: PASS');
for (const note of notes) console.log(`- ${note}`);
