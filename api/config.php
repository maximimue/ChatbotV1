<?php
// API configuration for chatbot.syltwerk.de
// Prefer setting the OPENAI_API_KEY via Plesk > Websites & Domains > Hosting & DNS > Apache & nginx settings
// or via Environment Variables. This file provides a fallback placeholder.

$OPENAI_API_KEY = getenv('OPENAI_API_KEY') ?: '';
$OPENAI_MODEL   = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';
$ALLOWED_ORIGIN = 'https://chatbot.syltwerk.de'; // CORS: allow same origin

// Safety: hard-limit max prompt size
$MAX_CONTEXT_CHARS = 12000;
$MAX_QUESTION_CHARS = 2000;
$MAX_HISTORY_DEPTH = (int) (getenv('MAX_HISTORY_DEPTH') ?: 20);
