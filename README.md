# Chatbot API (PHP) for chatbot.syltwerk.de

This folder contains a minimal PHP backend you can deploy under `/httpdocs/api` on Plesk.
It expects per-hotel configs already present under `/httpdocs/<tenant>/config.php` (e.g. `faehrhaus/config.php`),
and reads each hotel's `FAQ_FILE` from that config to ground answers.

## Endpoints

- `POST /api/ask.php?tenant=faehrhaus`
  - Body (JSON): `{ "question": "..." }`
  - Returns: `{ "answer": "...", "sources": [ { "title": "...", "url": "..." } ] }`

## Configure

1) In Plesk set an environment variable `OPENAI_API_KEY` (recommended), or edit `/api/config.php`
   and replace `PUT_YOUR_OPENAI_API_KEY_HERE` with your key.
2) Ensure each hotel's `config.php` exists and defines:
   - `$FAQ_FILE` — points to the hotel's Markdown knowledge base (e.g. `__DIR__.'/data/faq.md'`)
   - `$HOTEL_NAME` and `$HOTEL_URL` (optional, used for nice prompts and sources)
3) Update each hotel's `$API_URL` to your new endpoint, e.g.:
   - Fährhaus: `https://chatbot.syltwerk.de/api/ask.php?tenant=faehrhaus`
   - Aarnhoog: `https://chatbot.syltwerk.de/api/ask.php?tenant=aarnhoog`

## Notes

- Simple retrieval: We select relevant paragraphs from the hotel's FAQ based on word overlap
  to keep the model focused. For more advanced RAG, swap this for an embedding/cosine search.
- CORS is restricted to `https://chatbot.syltwerk.de`. If you host elsewhere, change `$ALLOWED_ORIGIN`.
- The response format matches your existing frontend expectation (`answer` + optional `sources`).

