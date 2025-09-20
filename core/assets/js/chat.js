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

  function appendMessage(text, from = 'bot') {
    const div  = document.createElement('div');
    div.classList.add('msg', from);
    const name = from === 'user' ? userName : botName;
    // HTML erlaubt für Bot-Antworten (Links etc.)
    div.innerHTML = `<div class="bubble"><strong>${name}:</strong> ${String(text).replace(/\n/g, '<br>')}</div>`;
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
