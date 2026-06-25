import os
from flask import Flask, request, jsonify, render_template, send_from_directory, abort
from werkzeug.utils import secure_filename
from dotenv import load_dotenv
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from create_db import Trip, Base
import json
from datetime import datetime

load_dotenv()
DATABASE_URL = os.environ.get('DATABASE_URL')
UPLOAD_DIR = os.environ.get('UPLOAD_DIR', 'uploads')

app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = UPLOAD_DIR

if not DATABASE_URL:
    raise RuntimeError('DATABASE_URL not set; copy .env.example to .env and edit')

engine = create_engine(DATABASE_URL)
Session = sessionmaker(bind=engine)

@app.route('/')
def index():
    session = Session()
    trips = session.query(Trip).order_by(Trip.uploaded_at.desc()).limit(100).all()
    return render_template('index.html', trips=trips)

@app.route('/trip/<int:trip_id>')
def trip_detail(trip_id):
    session = Session()
    trip = session.query(Trip).filter(Trip.id==trip_id).first()
    if not trip:
        abort(404)
    files = json.loads(trip.files or '[]')
    metadata = json.loads(trip.metadata or '{}')
    return render_template('trip.html', trip=trip, files=files, metadata=metadata)

@app.route('/uploads/<path:filename>')
def uploaded_file(filename):
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename, as_attachment=True)

@app.route('/api/trips/upload', methods=['POST'])
def api_upload_trip():
    # Expected fields per device README
    metadata = request.form.get('metadata')
    trip_id_field = request.form.get('trip_id') or (json.loads(metadata).get('trip_id') if metadata else None)
    device_id = request.form.get('device_id') or (json.loads(metadata).get('device_id') if metadata else None)

    if not trip_id_field or not device_id:
        return jsonify({'error':'trip_id and device_id required'}), 400

    saved_files = []
    dest_dir = os.path.join(app.config['UPLOAD_FOLDER'], secure_filename(device_id), secure_filename(trip_id_field))
    os.makedirs(dest_dir, exist_ok=True)

    for field in ['combined_geojson','gps_csv','sensor_events_csv','main_events_csv']:
        file = request.files.get(field)
        if file:
            filename = secure_filename(file.filename or f'{field}')
            path = os.path.join(dest_dir, filename)
            file.save(path)
            saved_files.append(os.path.relpath(path, app.config['UPLOAD_FOLDER']))

    # Additional files: any other uploaded files
    for key in request.files:
        if key in ['combined_geojson','gps_csv','sensor_events_csv','main_events_csv']:
            continue
        f = request.files.get(key)
        if f:
            filename = secure_filename(f.filename or key)
            path = os.path.join(dest_dir, filename)
            f.save(path)
            saved_files.append(os.path.relpath(path, app.config['UPLOAD_FOLDER']))

    # Store metadata and DB entry
    session = Session()
    trip = Trip(device_id=device_id, trip_id=trip_id_field, metadata=metadata or '{}', files=json.dumps(saved_files), uploaded_at=datetime.utcnow())
    session.add(trip)
    session.commit()

    return jsonify({'status':'ok','trip_db_id':trip.id}), 201

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8000)
