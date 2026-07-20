/**
 * Rendu Markdown minimal et sûr (échappe le HTML puis applique un
 * sous-ensemble) — portage de `renderMarkdown`/`inlineMarkdown`
 * (frontend-existant/app.js), utilisé pour les réponses de l'assistant IA.
 */
function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function inlineMarkdown(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/`([^`]+)`/g, '<code>$1</code>');
}

export function renderMarkdown(md) {
  const esc = escapeHtml(md);
  const lines = esc.split('\n');
  let html = '';
  let inUl = false;
  let inOl = false;

  const closeLists = () => {
    if (inUl) {
      html += '</ul>';
      inUl = false;
    }
    if (inOl) {
      html += '</ol>';
      inOl = false;
    }
  };

  for (const raw of lines) {
    const line = raw.trim();

    if (!line) {
      closeLists();
      continue;
    }
    if (/^[-*]\s+/.test(line)) {
      if (!inUl) {
        closeLists();
        html += '<ul>';
        inUl = true;
      }
      html += `<li>${inlineMarkdown(line.replace(/^[-*]\s+/, ''))}</li>`;
    } else if (/^\d+[.)]\s+/.test(line)) {
      if (!inOl) {
        closeLists();
        html += '<ol>';
        inOl = true;
      }
      html += `<li>${inlineMarkdown(line.replace(/^\d+[.)]\s+/, ''))}</li>`;
    } else if (/^#{1,6}\s+/.test(line)) {
      closeLists();
      html += `<p><strong>${inlineMarkdown(line.replace(/^#{1,6}\s+/, ''))}</strong></p>`;
    } else {
      closeLists();
      html += `<p>${inlineMarkdown(line)}</p>`;
    }
  }

  closeLists();
  return html;
}
