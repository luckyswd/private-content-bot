ahoy up
ahoy composer i

Start bot steps for local env:
1. ngrok http 80
2. Copy https url
3. Paste url in .env file in HOOK_URL=YOUR_URL
4. In browser start http://localhost/webhook/set
5. to check requests, open http://127.0.0.1:4040
6. Send a message to the bot

Settings TG
1) Создать 1 бота для получения сообщений из группы
2) Создать 2 бота для обработки событий
3) Создать приватную ГРУППУ(админка) и 2 добавить туда 2-х ботов и дать им права админа.