import random

TARGET_ROWS = 190
TARGET_ARR = 95
TARGET_DEP = 95
TARGET_ADULT = 24774
TARGET_CHILD = 757
TARGET_INFANT = 96
TARGET_TRANSIT = 825
TARGET_TRANSFER = 0
TARGET_CAP = 35763
TARGET_CARGO = 439798
TARGET_BAGGAGE = 285219

random.seed(42)

def distribute(total, count):
    base = total // count
    rem = total % count
    res = [base] * count
    for i in range(rem):
        res[i] += 1
    random.shuffle(res)
    return res

adults = distribute(TARGET_ADULT, TARGET_ROWS)
children = distribute(TARGET_CHILD, TARGET_ROWS)
infants = distribute(TARGET_INFANT, TARGET_ROWS)
transits = distribute(TARGET_TRANSIT, TARGET_ROWS)
caps = distribute(TARGET_CAP, TARGET_ROWS)
cargos = distribute(TARGET_CARGO, TARGET_ROWS)
baggages = distribute(TARGET_BAGGAGE, TARGET_ROWS)

cities = ['SUB', 'DPS', 'KNO', 'UPG', 'JOG', 'SRG', 'BPN', 'BDJ', 'MDC', 'LOP', 'PLM', 'BTJ', 'PKU', 'PDG', 'SOQ', 'AMQ', 'KOE', 'TRK']

rows_data = []

# Distribute 95 flights evenly across 24 hours (approx 4 flights per hour)
hours_arr = [i % 24 for i in range(95)]
hours_dep = [i % 24 for i in range(95)]
random.shuffle(hours_arr)
random.shuffle(hours_dep)
hours_dep[0] = 0  # Row 0: GA 894 at 00:10

no_actual_indices = set(random.sample(range(1, TARGET_ROWS), 25))

for i in range(TARGET_ROWS):
    is_dep = (i < 95)
    leg = 'D SCHED' if is_dep else 'A SCHED'
    
    if is_dep:
        h = hours_dep[i]
        m_sched = 10 if i == 0 else (i * 7) % 60
        m_act = 9 if i == 0 else (m_sched + (i % 15 - 5)) % 60
        city1 = 'CGK'
        city2 = 'SOQ' if i == 0 else cities[i % len(cities)]
        flt = 'GA 894' if i == 0 else f'GA {200 + i}'
    else:
        arr_idx = i - 95
        h = hours_arr[arr_idx]
        m_sched = (arr_idx * 11) % 60
        m_act = (m_sched + (arr_idx % 15 - 5)) % 60
        city1 = cities[arr_idx % len(cities)]
        city2 = 'CGK'
        flt = f'GA {100 + arr_idx}'

    sched_str = f'01-07-2026 {h:02d}:{m_sched:02d}'
    if i in no_actual_indices:
        act_str = '-'
    else:
        act_str = f'01-07-2026 {h:02d}:{m_act:02d}'

    c = caps[i]
    ad = adults[i]
    ch = children[i]
    inf = infants[i]
    tr = transits[i]
    pax_sum = ad + ch + inf
    load_pct = round((pax_sum / c) * 100, 1) if c > 0 else 0
    cg = cargos[i]
    bg = baggages[i]
    reg_code = chr(65 + (i % 26)) + chr(65 + ((i * 3) % 26))

    rows_data.append({
        'no': i + 1,
        'airline': 'Garuda Indonesia',
        'flight_no': flt,
        'paired_no': f'GA {500 + i}',
        'sibt_sobt': sched_str,
        'aibt_aobt': act_str,
        'leg': leg,
        'city1': city1,
        'city2': city2,
        'mtow': '79000',
        'reg_no': f'PK-G{reg_code}',
        'cap': c,
        'load': f'{load_pct}%',
        'adult': ad,
        'child': ch,
        'infant': inf,
        'transit': tr,
        'transfer': 0,
        'divert': 0,
        'miss': 0,
        'crw': 6,
        'ex_crw': 0,
        'cargo': cg,
        'baggage': bg,
        'pos': 0,
        'stand': f'T3-{1 + (i % 30):02d}',
        'runway': '25L' if i % 2 == 0 else '07R'
    })

html = []
html.append('<title>&nbsp;OASYS Flight Daily Report</title>')
html.append('<input type="hidden" name="BRANCH_CODE" value="CGK">')
html.append('<input type="hidden" name="transactions_dateFDR" value="2026/07/01 - 2026/07/01">')
html.append('<input type="hidden" name="OPERATOR" value="GA">')
html.append('<input type="hidden" name="SUFFIX" value="ALL">')
html.append('<input type="hidden" name="Leg" value="ALL">')
html.append('<input type="hidden" name="CATEGORY_CODE" value="ALL">')
html.append('<input type="hidden" name="REAL" value="YES">')
html.append('<CENTER><B>FLIGHT DAILY REPORT (LAPORAN HARIAN PENERBANGAN)<BR>TANGERANG BANTEN - SOEKARNO HATTA (CGK)<BR>TANGGAL 2026-07-01 s/d 2026-07-01<br>OPERATOR: GA<br>REALISASI: YES<br>TIPE DATA: OPERATIONAL DATA</B></CENTER><BR>')
html.append('<table width="100%" border="1" cellspacing="0" cellpadding="3" class="table">')
html.append('  <tr bgcolor="#F1F5F9">')
html.append('    <th>NO</th><th>AIR LINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>SIBT SOBT</th><th>AIBT AOBT</th><th>LEG</th><th>CITY 1</th><th>CITY 2</th><th>MTOW</th><th>REG. NO</th><th>CAP.</th><th>LOAD</th><th>ADULT</th><th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISS</th><th>CRW</th><th>EX. CRW</th><th>CAR. (KG)</th><th>BAGG. (KG)</th><th>POS (KG)</th><th>STAND</th><th>RUN WAY</th>')
html.append('  </tr>')

for r in rows_data:
    html.append(f'  <tr><td>{r["no"]}</td><td>{r["airline"]}</td><td>{r["flight_no"]}</td><td>{r["paired_no"]}</td><td>{r["sibt_sobt"]}</td><td>{r["aibt_aobt"]}</td><td>{r["leg"]}</td><td>{r["city1"]}</td><td>{r["city2"]}</td><td>{r["mtow"]}</td><td>{r["reg_no"]}</td><td>{r["cap"]}</td><td>{r["load"]}</td><td>{r["adult"]}</td><td>{r["child"]}</td><td>{r["infant"]}</td><td>{r["transit"]}</td><td>{r["transfer"]}</td><td>{r["divert"]}</td><td>{r["miss"]}</td><td>{r["crw"]}</td><td>{r["ex_crw"]}</td><td>{r["cargo"]}</td><td>{r["baggage"]}</td><td>{r["pos"]}</td><td>{r["stand"]}</td><td>{r["runway"]}</td></tr>')

html.append('</table>')

full_content = '\n'.join(html)

with open('storage/app/templates/CGK FDR.xls', 'w', encoding='utf-8') as f:
    f.write(full_content)

print(f'Successfully generated storage/app/templates/CGK FDR.xls with {len(rows_data)} rows.')
