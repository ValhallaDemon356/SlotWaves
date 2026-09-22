# SlotWaves ✈️

> **Airport Operational Slot & Flight Intelligence Platform**

SlotWaves is a Laravel-based web application for turning airport operational data and OASYS DAU reports into structured, interactive dashboards, operational analytics, and export-ready reports.

The application combines two main workflows:

- **Airport Slot Schedule** — PDF-based flight schedule ingestion, matching, timeline, capacity, parking-stand occupancy, and operational analysis.
- **DAU Analytics** — report-specific Excel ingestion for **DAU-01 through DAU-12**, with dashboards, filters, visual analytics, historical comparison, and exports.

---

## 🎯 What SlotWaves Does

SlotWaves is designed to reduce manual work when processing operational aviation data.

### Unified workflow

```text
SELECT TYPE DATA TO GENERATE
            ↓
        UPLOAD FILE
            ↓
     TEMPLATE VALIDATION
            ↓
          GENERATE
            ↓
 READ → EXTRACT → MATCH → NORMALIZE
            ↓
      CALCULATE / AGGREGATE
            ↓
         DASHBOARD
            ↓
      FILTER / ANALYZE
            ↓
       EXPORT REPORT
        ├── PDF
        ├── Excel
        └── CSV
```

The application follows a **report-driven** approach:

- The user selects the report type first.
- The selected report determines the parser and validation rules.
- The uploaded file is checked against the expected structure.
- The filename is not treated as the source of truth.
- Dashboard, KPI, charts, tables, and supported exports are derived from the processed dataset.

---

# 🚀 Main Features

## 1. Unified Report Generator

From the Home page, users can choose:

| # | Report Type | Focus |
|---:|---|---|
| 1 | **Airport Slot Schedule** | Flight schedule, timeline & capacity |
| 2 | **DAU-01** | Arus Lalu Lintas |
| 3 | **DAU-02** | Secara Total / Historical Comparison |
| 4 | **DAU-03** | Status Penerbangan |
| 5 | **DAU-04** | Asal / Tujuan |
| 6 | **DAU-04A** | Asal / Tujuan |
| 7 | **DAU-04B** | Asal / Tujuan (Airline / Operator) |
| 8 | **DAU-05** | Airline / Operator |
| 9 | **DAU-05A** | Airline / Operator (A) |
| 10 | **DAU-05B** | Airline / Operator (B) |
| 11 | **DAU-05C** | Airline / Operator (C) |
| 12 | **DAU-06** | Tipe Pesawat |
| 13 | **DAU-10** | Jam Puncak Pesawat / Penumpang |
| 14 | **DAU-10A** | Jam Puncak Pesawat / Penumpang (Terminal) |
| 15 | **DAU-10B** | Jam Puncak Pesawat / Penumpang (Block On/Off) |
| 16 | **DAU-11** | Data Statistik 1 (ARR/DEP/DOM/INT) |
| 17 | **DAU-12** | Data Statistik 2 (ARR/DEP/DOM/INT) |

---

# 📥 Upload & Template Validation

Each report type has its own expected source format.

| Report | Expected Input |
|---|---|
| Airport Slot Schedule | Existing Slot Schedule PDF format |
| DAU-01 to DAU-12 | Corresponding DAU Excel template/schema |

Validation is based on file structure and source content, including where applicable:

- extension / file signature
- required sheets
- expected headers
- grouped or multi-row headers
- required fields
- report-specific sections
- data types
- template compatibility

Example:

```text
Selected:
DAU-01

Uploaded:
DAU-04B workbook

→ INVALID TEMPLATE
```

Large DAU workbooks are supported by the report ingestion pipeline, including multi-month datasets where the underlying template remains compatible.

---

# ✈️ Airport Slot Schedule

Airport Slot Schedule is the original operational workflow of SlotWaves.

## Pipeline

```text
PDF
 ↓
READ
 ↓
EXTRACT
 ↓
MATCH
 ↓
TIMELINE
 ↓
CAPACITY
 ↓
EXPORT
```

## Main capabilities

- Arrival / Departure schedule analysis
- Airline and flight-number extraction
- Aircraft type matching
- Airport / route matching
- DOM / INT classification
- Flight pairing
- RON (Remain Over Night)
- OPC (Occupancy Parking Stand)
- Manual Aircraft Capacity / NAC
- Operating Hours
- 24-hour operational timeline
- Local (WIB) / UTC view
- Capacity status
- PDF export
- JPG export

## Aircraft Capacity

Operational aircraft demand is evaluated using:

```text
Aircraft Demand = Arrival + Departure + OPC
```

Example:

```text
Arrival   = 7
Departure = 6
OPC       = 2

Demand = 15 A/C

NAC = 6 A/C

15 / 6 A/C
→ OVER CAPACITY
```

OPC is used for parking-stand occupancy analysis and is not counted as a normal flight movement in the main Total Movements figure.

### Independent ARR / DEP status

Arrival and Departure are evaluated independently.

```text
ARR < ARR CAP → AVAILABLE
ARR = ARR CAP → MAX
ARR > ARR CAP → OVER
```

```text
DEP < DEP CAP → AVAILABLE
DEP = DEP CAP → MAX
DEP > DEP CAP → OVER
```

Example:

```text
ARR CAP = 30
DEP CAP = 30

ARR = 30 → MAX
DEP = 45 → OVER
```

One direction never changes the status of the other.

---

# 📊 DAU Analytics

DAU dashboards are **report-specific**. Each DAU has a dedicated parser and its own analytical presentation.

## DAU-01 — Arus Lalu Lintas

Focuses on traffic volume and movement detail.

Typical analytics include:

- Aircraft Movement
- Passenger Movement
- Baggage
- Cargo
- POS / mail where available
- Arrival / Departure
- Origin / Destination
- Flight-level operational records

### Passenger breakdown

When the source provides the required fields, Passenger mode can distinguish categories such as:

- Adult / Dewasa
- Child / Anak
- Infant / Bayi

The visual analytics should switch to passenger values instead of reusing aircraft values.

---

## DAU-02 — Secara Total

DAU-02 supports two workflows:

### Single Report

```text
1 DAU-02 file
      ↓
Generate
      ↓
Dashboard
```

### Historical Comparison

Multiple DAU-02 files can be compared when their periods are compatible.

Example:

```text
Period A → 2020
Period B → 2021
Period C → 2022
Period D → 2023
Period E → 2024
Period F → 2025
```

The historical comparison page contains:

1. **Baseline Period controls**
2. **Kinerja Operasional Bandara**
3. **Data Pergerakan Historis**
4. **Historical comparison table**
5. **PDF / CSV export**

### Baseline Period

The Baseline Period is the reference period for comparison calculations.

For six periods, choosing Period A means the comparison set contains the other five periods.

The baseline setting belongs to the comparison logic and must not replace the actual period values used by the operational charts.

### Kinerja Operasional Bandara

This section uses **three independent combo charts**:

1. **Pergerakan Penumpang**
   - Bar = actual passenger value
   - Line + points = passenger trend

2. **Pergerakan Pesawat**
   - Bar = actual aircraft movement
   - Line + points = aircraft trend

3. **Pergerakan Kargo**
   - Bar = actual cargo value
   - Line + points = cargo trend

Each chart has its own scale and its own metric.

### Data Pergerakan Historis

This section focuses on historical period-by-period distribution and supports:

- **Scope:** ALL / DOMESTIC / INTERNATIONAL
- **Direction:** ALL / ARRIVAL / DEPARTURE

Metric colors are semantically consistent:

```text
Passenger → Blue family
Aircraft  → Green family
Cargo     → Orange family
```

Domestic and International are distinguished using shades within the corresponding metric color family.

---

## DAU-03 — Status Penerbangan

Focuses on status / flight classification from the actual source data.

Visualizations depend on the fields available in the source report.

Typical filters can include:

- status/category
- arrival/departure
- supported flight classification

---

## DAU-04 — Asal / Tujuan

Focuses on origin and destination distribution.

Typical analytics:

- top origin / arrival
- top destination / departure
- aircraft-based comparison
- passenger-based comparison

Filters can include:

- top N
- direction
- airport search
- supported domestic / international scope

---

## DAU-04A — Asal / Tujuan

Provides an operator-to-airport / route relationship view using the fields present in the source.

Typical visualization:

- hierarchical distribution
- route/operator comparison

Filters should visibly affect the corresponding analytical output and detail records.

---

## DAU-04B — Asal / Tujuan (Airline / Operator)

Provides an Airport × Airline / Operator relationship view.

Typical visualization:

```text
Airport × Airline / Operator
        ↓
Traffic / Flight Volume
```

A matrix / heatmap representation can be used when supported by the source data.

---

## DAU-05 — Airline / Operator

Focuses on airline/operator distribution.

The main visualization uses a **Pareto-style analysis**:

- bars → airline/operator volume
- cumulative line → cumulative share

Depending on the available fields, the metric can represent:

- aircraft
- passenger
- baggage
- cargo
- POS

---

## DAU-05A — Airline / Operator (A)

Focuses on airline/operator operational data such as crew and movement fields when provided by the source.

Typical visualization:

- grouped bar comparison

Typical filters:

- airline/operator
- ARR
- DEP
- TOTAL

---

## DAU-05B — Airline / Operator (B)

Focuses on terminal × airline/operator distribution.

Typical visualization:

**Stacked Bar by Terminal**

```text
Terminal
  ↓
Airline / Operator
  ↓
Traffic volume
```

Selecting a terminal should update the related KPI, chart, and detail records.

---

## DAU-05C — Airline / Operator (C)

Focuses on airline/operator comparison profiles using the actual DAU-05C schema.

The dashboard should only calculate metrics that are supported by the uploaded source.

If both Passenger and Seat Capacity are available, Load Factor can be derived as:

```text
Load Factor =
Passenger / Seat Capacity × 100
```

If Seat Capacity is not available, Load Factor must not be fabricated.

---

# 🛫 DAU-06 — Tipe Pesawat

Focuses on aircraft type distribution.

Typical analysis:

- aircraft type volume
- passenger volume by aircraft type
- supported WTC information
- fleet distribution where source/master data permits

Aircraft Category (Regional) is not part of the current dashboard presentation.

---

# ⏱️ DAU-10 — Jam Puncak Pesawat / Penumpang

Focuses on hourly peak analysis.

Typical output:

- peak aircraft hour
- peak passenger hour
- total aircraft
- total passenger
- hourly distribution

Filters depend on the report schema and may include:

- scope
- direction
- hour
- terminal where supported
- flight type

The current dashboard separates Aircraft and Passenger visual analysis instead of forcing them into one metric display.

---

# 🏢 DAU-10A — Jam Puncak Menurut Terminal

DAU-10A is designed for **Time × Terminal** and operational hourly analysis.

## Metric modes

Available source metrics may include:

- Aircraft
- Passenger
- Crew

The dashboard must switch to the selected metric's own source fields.

### Aircraft mode

Aircraft mode supports:

- Aircraft Arrival
- Aircraft Departure
- Aircraft Capacity / NAC
- Operating Hours
- MAX / OVER / AVAILABLE
- Capacity envelope
- hourly capacity analysis

### Passenger / Crew mode

Passenger and Crew modes use their own source data and do not inherit aircraft capacity fields.

---

## DAU-10A — Distribusi Per Jam

The hourly chart uses a two-direction operational layout:

```text
        ARRIVAL
           ↑

     hourly demand

────── TIME AXIS ──────

     hourly demand

           ↓
       DEPARTURE
```

### Capacity envelope

One connected dashed operational envelope represents:

- **Top** → Arrival Capacity
- **Bottom** → Departure Capacity
- **Left** → Operating Hours Start
- **Right** → Operating Hours End

Arrival and Departure capacity values are independently editable.

### Terminal filter

The chart can be scoped to:

- ALL TERMINALS
- selected terminal

The selected terminal affects the analytical dataset consistently.

### Average By Days

Aircraft Distribution Per Hour also supports average-by-days analysis for multi-day data:

- Original Data
- 5 Days
- 15 Days
- 30 Days
- 60 Days
- Custom

The transformation is applied to the hourly aircraft values while preserving the original source data.

The averaging rule uses upward rounding:

```text
average = ceil(hourlyValue / N)
```

Example:

```text
338.9 → 339
```

Reset returns the chart to the original data.

---

# 🧭 DAU-10B — Block On / Block Off

Focuses on hourly:

**BLOCK ON (DTG) vs BLOCK OFF (BRK)**

Typical visualization:

- grouped hourly bars
- peak-hour indicators
- hourly summary table

The dashboard can support metric-specific data modes where the source contains the corresponding passenger / aircraft fields.

Filters may include:

- metric
- terminal
- hour
- operation
- direction
- domestic / international where supported

Rapid filter changes are handled so the latest selected state remains the authoritative chart state.

---

# 📈 DAU-11 — Data Statistik 1

Focuses on statistical ARR / DEP / DOM / INT analysis using the actual DAU-11 fields.

Normalized fields are maintained so the same filter and aggregation engine can operate consistently.

---

# 📊 DAU-12 — Data Statistik 2

Focuses on:

```text
ARRIVAL vs DEPARTURE
+
DOMESTIC vs INTERNATIONAL
```

The dashboard supports metric-specific visualization where the source provides:

- Aircraft
- Passenger

and can present grouped comparisons across ARR/DEP and DOM/INT.

---

# 🔎 Universal Filter Architecture

SlotWaves uses a common analytical pipeline:

```text
Raw Parsed Records
        ↓
Selected Filters
        ↓
Filtered Dataset
        ├── KPI
        ├── Charts
        ├── Tables
        ├── Tooltips
        └── Supported Exports
```

The objective is to keep the visible dashboard synchronized.

Typical filters across DAU dashboards include:

- Metric
- Direction
- Domestic / International
- Airline / Operator
- Airport / Route
- Aircraft Type
- Terminal
- Hour
- Operation
- Search

Active filters should be visibly distinguishable from the default state.

---

# 📤 Export

Where supported by a report, SlotWaves provides:

- **PDF**
- **CSV**
- **Excel**

## PDF principles

PDF export should preserve:

- report metadata
- filters / scope
- KPI summaries
- charts / visual analytics
- detail tables
- comparison information
- report context

The exported values must come from the same analytical dataset used by the dashboard.

---

# 🗄️ Database

SlotWaves uses **PostgreSQL via Supabase** for production persistence.

The application follows a non-destructive database approach and evolves the schema through migrations.

Production data must not be replaced simply to introduce or modify a report type.

---

# 🏗️ Architecture

```text
                    ┌───────────────────────────┐
                    │           HOME            │
                    │ Select Type to Generate   │
                    └─────────────┬─────────────┘
                                  │
                 ┌────────────────┴────────────────┐
                 │                                 │
                 ▼                                 ▼
        Airport Slot Schedule                     DAU
                 │                          DAU-01 … DAU-12
                 ▼                                 │
          Template Validator                        ▼
                 │                           Template Validator
                 ▼                                 │
            PDF Parser                             ▼
                 │                           Report-specific
                 ▼                               Parser
              Matching                             │
                 │                                 ▼
                 ▼                              Normalize
             Timeline                              │
                 │                                 ▼
                 ▼                              Aggregate
             Capacity                              │
                 │                                 │
                 └───────────────┬─────────────────┘
                                 ▼
                             DASHBOARD
                                 │
                       ┌─────────┴─────────┐
                       ▼                   ▼
                    FILTER             ANALYTICS
                       │                   │
                       └─────────┬─────────┘
                                 ▼
                              EXPORT
```

---

# 📁 Project Structure

```text
SlotWaves/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       └── DauDashboardController.php
│   │
│   ├── Services/
│   │   └── Dau/
│   │       ├── Parsers/
│   │       │   ├── DAU1Parser.php
│   │       │   ├── DAU2Parser.php
│   │       │   ├── DAU3Parser.php
│   │       │   ├── DAU4Parser.php
│   │       │   ├── DAU4AParser.php
│   │       │   ├── DAU4BParser.php
│   │       │   ├── DAU5Parser.php
│   │       │   ├── DAU5AParser.php
│   │       │   ├── DAU5BParser.php
│   │       │   ├── DAU5CParser.php
│   │       │   ├── DAU6Parser.php
│   │       │   ├── DAU10Parser.php
│   │       │   ├── DAU10AParser.php
│   │       │   ├── DAU10BParser.php
│   │       │   ├── DAU11Parser.php
│   │       │   └── DAU12Parser.php
│   │       │
│   │       ├── ReportTemplateRegistry.php
│   │       └── TemplateValidator.php
│   │
│   └── Models/
│
├── database/
│   └── migrations/
│
├── resources/
│   ├── views/
│   │   ├── components/
│   │   ├── dau/
│   │   └── ...
│   ├── js/
│   └── css/
│
├── routes/
│   └── web.php
│
├── storage/
│   └── app/
│       └── templates/
│
├── public/
│   └── build/
│
├── composer.json
├── package.json
└── README.md
```

---

# 🧩 Key Components

### `DauDashboardController.php`

Connects report data, filtering, dashboard views, and supported exports.

### `TemplateValidator.php`

Validates source files against the selected report type.

### `ReportTemplateRegistry.php`

Central registry for report types, template rules, parsers, dashboards, and report capabilities.

### `app/Services/Dau/Parsers/`

Contains report-specific parser implementations.

Each DAU parser is responsible for understanding its own source structure.

---

# 🛠️ Technology Stack

## Backend

- PHP
- Laravel
- Laravel Blade
- Laravel Breeze where authentication scaffolding is used

## Frontend

- Blade
- Tailwind CSS
- Alpine.js
- JavaScript
- Vite

## Data & Reporting

- Excel ingestion for DAU reports
- PDF ingestion for Airport Slot Schedule
- PDF / CSV / Excel export where supported

## Database

- PostgreSQL
- Supabase

## Deployment

- Vercel
- GitHub

---

# 🔐 Data Integrity Principles

SlotWaves is designed around a few important rules.

### 1. Source-first

Dashboard values should originate from the uploaded source data or a deterministic enrichment source.

### 2. No fabricated analytics

If a value is not present and cannot be derived legitimately, the application should represent it as unavailable rather than inventing it.

### 3. One analytical dataset

The same filtered dataset should drive:

- KPI
- charts
- tables
- tooltips
- supported exports

### 4. Report-specific parsing

Different DAU reports must not be forced into a single generic source schema when their structures differ.

### 5. Preserve stable workflows

DAU development should not unnecessarily break Airport Slot Schedule functionality.

---

# 🧪 Testing & Validation

The repository contains report-specific and regression-oriented validation.

Important test areas include:

- Airport Slot Schedule ingestion
- Arrival / Departure parsing
- Flight pairing
- RON
- OPC
- Aircraft Capacity / NAC
- Operating Hours
- DAU template validation
- DAU parser behavior
- Filter synchronization
- Dashboard calculations
- DAU-10A metric separation
- DAU-10B filter stability
- DAU-12 visual analytics
- PDF export

Typical local verification commands:

```bash
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test
npm ci
npm run build
```

---

# 🚀 Local Development

## Requirements

- PHP compatible with the project's Laravel version
- Composer
- Node.js / npm
- PostgreSQL or Supabase
- Git

## Setup

```bash
git clone https://github.com/ValhallaDemon356/SlotWaves.git
cd SlotWaves
```

Install dependencies:

```bash
composer install
npm ci
```

Create environment configuration:

```bash
cp .env.example .env
php artisan key:generate
```

Configure database credentials through environment variables.

Run migrations:

```bash
php artisan migrate
```

Build assets:

```bash
npm run build
```

Run locally:

```bash
php artisan serve
```

---

# ☁️ Production

Current production architecture:

```text
GitHub
   ↓
Vercel
   ↓
Laravel Application
   ↓
Supabase PostgreSQL
```

Keep secrets in environment variables.

Never commit:

```text
.env
.env.local
.env.production
```

Never commit:

- database passwords
- Supabase secret/service keys
- JWT secrets
- Vercel tokens
- GitHub tokens
- other application credentials

---

# 📦 Deployment Checklist

```text
[ ] Environment variables configured
[ ] Database connection verified
[ ] Migrations verified
[ ] Composer dependencies installed
[ ] Frontend dependencies installed
[ ] Production build succeeds
[ ] Routes verified
[ ] DAU templates validated
[ ] Airport Slot Schedule tested
[ ] PDF export tested
[ ] Master Data tested
[ ] No secrets committed
[ ] GitHub main updated
[ ] Vercel deployment completed
[ ] Production smoke test completed
```

---

# 🛡️ Database Safety

SlotWaves uses migrations for schema evolution.

Avoid destructive production actions such as:

```text
DROP DATABASE
DROP ALL TABLES
TRUNCATE production data
Recreate the entire Supabase project
```

unless explicitly approved and required.

---

# 📌 Recent Development Highlights

Recent work in the repository has focused on:

- DAU-02 Single Report and Historical Comparison
- DAU-02 Baseline, historical filters, and PDF visual output
- DAU-02 three independent Passenger / Aircraft / Cargo combo charts
- DAU-02 historical chart color consistency
- large DAU Excel upload handling
- DAU-05 series visual analytics
- DAU-05C analytics repair
- DAU-10A Average By Days
- DAU-10A terminal capacity analysis
- DAU-10B Block On / Block Off analytics and rapid-filter stability
- DAU-12 visual analytics repair
- universal DAU filter and synchronization fixes

---

# 🔭 Roadmap

Potential future work:

- deeper DAU drill-down
- additional OASYS template coverage
- richer terminal analytics
- report versioning
- audit trail
- role-based access control
- scheduled report generation
- advanced capacity analytics
- more export formats

---

# 🤝 Development Principles

### Accuracy over decoration

A chart is useful only when the values can be traced back to the source data.

### Report-specific analytics

Different reports answer different operational questions and therefore require different visualizations.

### One source of truth

The report type, parsed data, filter state, dashboard, and export should remain synchronized.

### Incremental development

New features should extend stable functionality rather than unnecessarily rewriting it.

---

# 📄 License

This repository currently does not declare a formal open-source license. Add a license file before distributing the project under explicit open-source terms.

---

# 👨‍💻 Project

**SlotWaves**  
Airport Operational Slot & Flight Intelligence

Repository: https://github.com/ValhallaDemon356/SlotWaves

> **SlotWaves transforms operational aviation data into readable, interactive, and report-ready airport intelligence.**
