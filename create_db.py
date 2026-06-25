from sqlalchemy import create_engine, Column, Integer, String, Text, DateTime
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker
from datetime import datetime
import os
from dotenv import load_dotenv

load_dotenv()
DATABASE_URL = os.environ.get('DATABASE_URL')

Base = declarative_base()

class Trip(Base):
    __tablename__ = 'trips'
    id = Column(Integer, primary_key=True)
    device_id = Column(String(128), index=True)
    trip_id = Column(String(128), index=True)
    metadata = Column(Text)
    files = Column(Text)
    uploaded_at = Column(DateTime, default=datetime.utcnow)

def main():
    if not DATABASE_URL:
        print('Set DATABASE_URL in environment or .env')
        return
    engine = create_engine(DATABASE_URL)
    Base.metadata.create_all(engine)
    print('DB initialized')

if __name__ == '__main__':
    main()
