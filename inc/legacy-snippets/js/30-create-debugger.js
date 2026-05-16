// ---- Debug switch (URL ?debug=1 or localStorage) ----
const DEBUG_KEY = 'wsbDebug';
window.WSB_DEBUG =
  new URLSearchParams(location.search).has('debug') ||
  localStorage.getItem(DEBUG_KEY) === '1';

window.enableWSBDebug  = () => { localStorage.setItem(DEBUG_KEY, '1'); location.reload(); };
window.disableWSBDebug = () => { localStorage.removeItem(DEBUG_KEY);   location.reload(); };

// ---- Namespaced logger ----
function makeLogger(ns = 'WSB') {
  const tag = `[${ns}]`;
  const guard = (fn) => (...a) => { if (window.WSB_DEBUG) fn(tag, ...a); };
  return {
    log: guard(console.log.bind(console)),
    info: guard(console.info.bind(console)),
    warn: guard(console.warn.bind(console)),
    error: guard(console.error.bind(console)),
    group: guard(console.group ? console.group.bind(console) : console.log.bind(console)),
    groupEnd: () => { if (window.WSB_DEBUG && console.groupEnd) console.groupEnd(); },
  };
}
window.WSB = { makeLogger }; // expose globally
