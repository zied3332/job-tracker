import csv
from datetime import datetime

# Explicit category mapping (including 'O')
CATEGORY_MAP = {
    'E': 'Emergency',
    'H': 'Hazardous',
    'R': 'Repair',
    'F': 'Flooring',
    'O': 'Other'  # Explicitly added
}

def get_category(job_number):
    if not job_number:
        return 'Other'
    last_char = job_number.strip()[-1].upper()
    return CATEGORY_MAP.get(last_char, 'Other')  # Fallback to 'Other'

def format_datetime(date_str):
    try:
        dt = datetime.strptime(date_str.strip(), "%m/%d/%Y %I:%M %p")
        return dt.strftime('%Y-%m-%d %H:%M:%S')
    except Exception:
        return "NULL"

with open('job_data.csv', mode='r', encoding='utf-8') as file:
    reader = csv.reader(file)
    header = next(reader)

    for row in reader:
        if len(row) < 9:
            continue  # Skip incomplete rows

        address = row[0].strip('"')
        city = row[1].strip()
        claim_number = row[2].strip()
        created_at = format_datetime(row[3])
        customer = row[4].strip()
        name = row[5].strip()
        job_number = row[6].strip()
        state = row[7].strip()
        status = row[8].strip()
        category = get_category(job_number)

        sql = f"""INSERT INTO job (
            job_number, claim_number, address, city, state, status,
            category, created_at, name, customer
        ) VALUES (
            '{job_number}', '{claim_number}', '{address}', '{city}', '{state}',
            '{status}', '{category}', '{created_at}', '{name}', '{customer}'
        );"""

        print(sql)
