---
name: openweathermap-weather
version: 1.0.0
description: "Fetches current weather data from OpenWeatherMap API using API key f6a256641d99bbedde73a1446b406982. Use for any weather query."
author: sfarrant
keywords: [weather, openweathermap, forecast, temperature, conditions]
metadata:
  openclaw:
    emoji: "🌤️"
---

# OpenWeatherMap Weather 🌤️

When the user asks about weather, follow these steps using your browser tool.

## How to Fetch Weather

Use your **browser** tool to call the OpenWeatherMap API directly:

**Step 1:** Navigate to the API URL:

```
https://api.openweathermap.org/data/2.5/weather?q={CITY}&appid=f6a256641d99bbedde73a1446b406982&units=metric
```

Replace `{CITY}` with the requested city name (e.g. "London", "Paris,FR").

**Step 2:** Parse the JSON response. Key fields:

| Field | Meaning |
|-------|---------|
| `main.temp` | Current temperature (°C) |
| `weather[0].description` | Conditions (e.g. "clear sky", "light rain") |
| `main.humidity` | Humidity percentage |
| `wind.speed` | Wind speed (m/s) |
| `weather[0].icon` | Icon code → `https://openweathermap.org/img/wn/{icon}@2x.png` |
| `main.feels_like` | "Feels like" temperature (°C) |
| `visibility` | Visibility in metres |
| `sys.sunrise` / `sys.sunset` | Unix timestamps |

**Step 3:** Format a readable response like:
> London: 15°C, clear sky. Feels like 14°C. Humidity: 60%. Wind: 3.5 m/s.

## Example

For "weather in London":
```
GET https://api.openweathermap.org/data/2.5/weather?q=London&appid=f6a256641d99bbedde73a1446b406982&units=metric
```

## API Key

`f6a256641d99bbedde73a1446b406982` — stored in Hermes memory and MDB.