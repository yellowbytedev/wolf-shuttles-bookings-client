#!/usr/bin/env node

import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const failures = [];
const notes = [];
const activeFiles = [];

function addIfFile(relative) {
  const absolute = path.join(root, relative);
  if (fs.existsSync(absolute) && fs.statSync(absolute).isFile()) activeFiles.push(absolute);
}

function walk(directory, predicate) {
  if (!fs.existsSync(directory)) return;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    if (['.git', 'node_modules', 'vendor', '.kilo'].includes(entry.name)) continue;
    const absolute = path.join(directory, entry.name);
    if (entry.isDirectory()) walk(absolute, predicate);
    else if (predicate(absolute)) activeFiles.push(absolute);
  }
}

addIfFile('AGENTS.md');
walk(path.join(root, 'docs', 'codex'), file => file.endsWith('.md'));
walk(path.join(root, '.agents', 'skills'), file => file.endsWith('SKILL.md'));

const agentFile = path.join(root, 'AGENTS.md');
if (!fs.existsSync(agentFile)) {
  failures.push('missing root AGENTS.md');
} else if (fs.statSync(agentFile).size > 12 * 1024) {
  failures.push('root AGENTS.md exceeds 12 KiB');
}

const skillRoot = path.join(root, '.agents', 'skills');
const skillNames = new Map();
if (fs.existsSync(skillRoot)) {
  for (const folder of fs.readdirSync(skillRoot)) {
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
    if (!description || description.includes('TODO')) failures.push(`${path.relative(root, skillFile)} needs a final description`);
    if (name) {
      if (skillNames.has(name)) failures.push(`duplicate skill name ${name}`);
      skillNames.set(name, skillFile);
    }
    const yaml = path.join(skillRoot, folder, 'agents', 'openai.yaml');
    if (!fs.existsSync(yaml)) failures.push(`${path.relative(root, skillFile)} is missing agents/openai.yaml`);
  }
}

const forbiddenOrigins = [
  'http://wolfshuttles.local',
  'http://bookings.wolfshuttles.local',
  'localhost'
];
const secretPatterns = [
  /sk-[A-Za-z0-9_-]{20,}/,
  /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
  /Authorization:\s*Bearer\s+[A-Za-z0-9._-]{16,}/i
];

for (const file of [...new Set(activeFiles)]) {
  const source = fs.readFileSync(file, 'utf8');
  const relative = path.relative(root, file);
  if (source.includes('[TODO')) failures.push(`${relative} contains a TODO placeholder`);
  for (const origin of forbiddenOrigins) {
    if (source.includes(origin)) failures.push(`${relative} contains forbidden active origin: ${origin}`);
  }
  for (const pattern of secretPatterns) {
    if (pattern.test(source)) failures.push(`${relative} resembles committed secret material`);
  }
  for (const match of source.matchAll(/\[[^\]]*\]\(([^)]+)\)/g)) {
    const target = match[1].trim().split('#')[0];
    if (!target || /^(?:https?:|mailto:|#)/.test(target) || target.startsWith('/')) continue;
    const resolved = path.resolve(path.dirname(file), target.replace(/^<|>$/g, ''));
    if (!fs.existsSync(resolved)) failures.push(`${relative} has unresolved link: ${target}`);
  }
}

notes.push(`${new Set(activeFiles).size} active guidance files checked`);
notes.push(`${skillNames.size} repo-local skills checked`);

if (failures.length) {
  console.error('Codex operating-layer audit: FAIL');
  for (const failure of failures) console.error(`- ${failure}`);
  process.exit(1);
}

console.log('Codex operating-layer audit: PASS');
for (const note of notes) console.log(`- ${note}`);
