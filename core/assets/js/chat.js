/*
 * JavaScript-Logik für den Chatbot.
 * - Holt den Bot-Namen aus window.CHATBOT.botName (aus PHP-Config)
 * - Zeigt einen "… schreibt"-Indikator während der Antwortgenerierung
 * - User rechts, Bot links (Styling via CSS)
 */

document.addEventListener('DOMContentLoaded', () => {
  const form    = document.getElementById('chat-form');
  const input   = document.getElementById('chat-input');
  const log     = document.getElementById('chat-log');
  const send    = document.getElementById('chat-send');
  const privacy = document.getElementById('privacy-confirm');

  const botName = (window.CHATBOT && window.CHATBOT.botName) ? String(window.CHATBOT.botName) : 'Bot';
  const userName = 'Du';

  const ALLOWED_BOT_TAGS = new Set(['A', 'BR', 'STRONG', 'EM', 'UL', 'OL', 'LI', 'P']);
  const ALLOWED_ATTRIBUTES = {
    A: new Set(['href', 'title'])
  };

  function sanitizeBotNode(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      return document.createTextNode(node.textContent || '');
    }
    if (node.nodeType !== Node.ELEMENT_NODE) {
      return document.createTextNode('');
    }

    const tagName = node.tagName.toUpperCase();
    if (!ALLOWED_BOT_TAGS.has(tagName)) {
      return document.createTextNode(node.textContent || '');
    }

    const cleanEl = document.createElement(tagName.toLowerCase());

    if (ALLOWED_ATTRIBUTES[tagName]) {
      for (const attr of Array.from(node.attributes)) {
        const attrName = attr.name.toLowerCase();
        if (!ALLOWED_ATTRIBUTES[tagName].has(attrName)) {
          continue;
        }

        let value = attr.value || '';
        if (tagName === 'A' && attrName === 'href') {
          const trimmed = value.trim();
          const lower = trimmed.toLowerCase();
          if (!(lower.startsWith('http://') || lower.startsWith('https://') || lower.startsWith('mailto:') || lower.startsWith('tel:'))) {
            continue;
          }
          value = trimmed;
        }

        cleanEl.setAttribute(attrName, value);
      }
    }

    if (tagName === 'A') {
      cleanEl.setAttribute('rel', 'noopener noreferrer');
      cleanEl.setAttribute('target', '_blank');
    }

    for (const child of Array.from(node.childNodes)) {
      const sanitizedChild = sanitizeBotNode(child);
      if (sanitizedChild) {
        cleanEl.appendChild(sanitizedChild);
      }
    }
    return cleanEl;
  }

  function sanitizeBotHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const fragment = document.createDocumentFragment();
    for (const node of Array.from(template.content.childNodes)) {
      const sanitized = sanitizeBotNode(node);
      if (sanitized) {
        fragment.appendChild(sanitized);
      }
    }
    return fragment;
  }

  function appendMessage(text, from = 'bot') {
    const div  = document.createElement('div');
    div.classList.add('msg', from);
    const name = from === 'user' ? userName : botName;

    const bubble = document.createElement('div');
    bubble.classList.add('bubble');

    const label = document.createElement('strong');
    label.textContent = `${name}:`;
    bubble.appendChild(label);
    bubble.appendChild(document.createTextNode(' '));

    if (from === 'user') {
      const safeText = String(text);
      const parts = safeText.split(/\r?\n/);
      parts.forEach((part, index) => {
        bubble.appendChild(document.createTextNode(part));
        if (index < parts.length - 1) {
          bubble.appendChild(document.createElement('br'));
        }
      });
    } else {
      const fragment = sanitizeBotHtml(String(text));
      bubble.appendChild(fragment);
    }

    div.appendChild(bubble);
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
  }

  // --- Typing Indicator ---
  function showTypingIndicator() {
    removeTypingIndicator(); // sicherheitshalber nur 1 Indikator
    const typing = document.createElement('div');
    typing.id = 'typing-indicator';
    typing.className = 'msg bot';
    typing.innerHTML = `<div class="bubble"><em>${botName} schreibt…</em></div>`;
    log.appendChild(typing);
    log.scrollTop = log.scrollHeight;
  }
  function removeTypingIndicator() {
    const typing = document.getElementById('typing-indicator');
    if (typing) typing.remove();
  }
  // ------------------------

  // Datenschutzzustimmung steuert Sendebutton
  if (privacy) {
    send.disabled = !privacy.checked;
    privacy.addEventListener('change', () => {
      send.disabled = !privacy.checked;
    });
  }

  form.addEventListener('submit', (ev) => {
    ev.preventDefault();
    const question = (input.value || '').trim();
    if (!question) return;

    appendMessage(question, 'user');
    input.value = '';
    send.disabled = true;
    showTypingIndicator();

    fetch('chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ question })
    })
    .then(res => res.json())
    .then(data => {
      removeTypingIndicator();
      const answer = data && data.answer ? data.answer : 'Entschuldigung, keine Antwort erhalten.';
      appendMessage(answer, 'bot');
      // Quellenanzeige ist bewusst deaktiviert
    })
    .catch(() => {
      removeTypingIndicator();
      appendMessage('Entschuldigung, es gab ein technisches Problem.', 'bot');
    })
    .finally(() => {
      send.disabled = privacy ? !privacy.checked : false;
    });
  });
});
