# MEMORY.md

## Habits & Conventions

- After finishing any development work on sysmonv2 (or any C++/Qt project), **run the app to verify it works.** Build first, then launch it if possible.

## 🌤️ Weather Skill

When the user asks about weather:
1. Read `skills/openweathermap-weather/SKILL.md` for API details
2. Use the **browser tool** to navigate to the OpenWeatherMap API URL with the city and API key `REDACTED-OPENWEATHERMAP-API-KEY`
3. Parse the JSON response and format a readable answer
