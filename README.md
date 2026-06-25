# Drillmaschinen Portal (Web UI)

Minimal Flask web app to receive trip uploads from the ESP32 device.

API endpoint:
- `POST /api/trips/upload` — accepts multipart/form-data as described in the device README.

Requirements:
- Python 3.8+
- MySQL server

Quickstart:
1. Create a Python virtualenv and install dependencies:
```bash
python -m venv .venv
source .venv/bin/activate  # or .\.venv\Scripts\activate on Windows
pip install -r requirements.txt
```
2. Configure database in `.env` (see `.env.example`).
3. Initialize DB:
```bash
python create_db.py
```
4. Run the app:
```bash
flask run --host=0.0.0.0 --port=8000
```
