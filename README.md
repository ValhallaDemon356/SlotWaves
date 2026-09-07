# SlotWaves ✈️

> **Airport Operational Slot & Flight Intelligence Platform**

SlotWaves is a web-based airport operations and report-generation platform designed to transform flight schedules and OASYS DAU datasets into structured operational dashboards, interactive analytics, and exportable reports.

The application combines **Airport Slot Schedule intelligence** with **Data Angkutan Udara (DAU) analytics** in a single workflow. Users choose the report type, upload the required source file, validate the template, generate the report, analyze the resulting dashboard, and export the final report.

---

## 🎯 Core Concept

SlotWaves follows a unified report-generation workflow:

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

The important principle is:

- **The user's selected report type determines the pipeline.**
- **The uploaded file content determines whether the template is valid.**
- **The filename is not used as the source of truth.**

For example, a valid DAU-1 workbook renamed to another filename can still be accepted when its actual structure matches the DAU-1 schema.

---

## 🚀 Main Features

### Unified Report Generator

The Home page acts as the entry point for all reporting workflows.

Users can select:

1. Airport Slot Schedule
2. DAU1 — Arus Lalu Lintas
3. DAU2 — Secara Total
4. DAU3 — Status Penerbangan
5. DAU4 — Asal/Tujuan
6. DAU4A — Asal/Tujuan
7. DAU4B — Asal/Tujuan (Airline/Operator)
8. DAU5 — Airline Operator
9. DAU5A — Airline Operator (A)
10. DAU5B — Airline Operator (B)
11. DAU5C — Airline Operator (C)
12. DAU6 — Tipe Pesawat
13. DAU10 — Jam Puncak Pesawat/Penumpang
14. DAU10A — Jam Puncak Pesawat/Penumpang (Terminal)
15. DAU10B — Jam Puncak Pesawat/Penumpang (Block On/Off)
16. DAU11 — Data Statistik 1 (ARR/DEP/DOM/INT)
17. DAU12 — Data Statistik 2 (ARR/DEP/DOM/INT)

---

# 📥 Input & Template Validation

Each report type has its own required source format.

| Report | Required Source |
|---|---|
| Airport Slot Schedule | Existing Airport Slot Schedule PDF format |
| DAU1 | DAU-1 Excel template/schema |
| DAU2 | DAU-2 Excel template/schema |
| DAU3 | DAU-3 Excel template/schema |
| DAU4 | DAU-4 Excel template/schema |
| DAU4A | DAU-4A Excel template/schema |
| DAU4B | DAU-4B Excel template/schema |
| DAU5 | DAU-5 Excel template/schema |
| DAU5A | DAU-5A Excel template/schema |
| DAU5B | DAU-5B Excel template/schema |
| DAU5C | DAU-5C Excel template/schema |
| DAU6 | DAU-6 Excel template/schema |
| DAU10 | DAU-10 Excel template/schema |
| DAU10A | DAU-10A Excel template/schema |
| DAU10B | DAU-10B Excel template/schema |
| DAU11 | DAU-11 Excel template/schema |
| DAU12 | DAU-12 Excel template/schema |

### Strict Validation

SlotWaves validates uploaded files by their actual content and structure.

Validation can include:

- file extension
- workbook/file signature
- expected header structure
- required columns
- grouped/multi-row headers
- data sections
- data types
- report-specific compatibility

Wrong templates are rejected before generation.

Example:

```text
Selected:
DAU1 — Arus Lalu Lintas

Uploaded:
DAU-5 workbook

Result:
❌ INVALID TEMPLATE

Expected:
DAU-1 structure
```

---

# ✈️ Airport Slot Schedule

Airport Slot Schedule is the original operational workflow of SlotWaves and remains separate from the DAU reporting pipeline.

### Pipeline

```text
PDF
 ↓
Read
 ↓
Extract
 ↓
Match
 ↓
Timeline
 ↓
Capacity
 ↓
Export
```

### Main Operational Features

- Arrival / Departure analysis
- Flight number and airline identification
- Aircraft type matching
- Airport matching
- DOM / INT classification
- Flight pairing
- RON (Remain Over Night)
- OPC (Occupancy Parking Stand)
- Manual Aircraft Capacity / NAC
- Operating Hours
- 24-hour timeline
- Local / UTC display
- Capacity status
- Cargo handling rules
- PDF export
- JPG export

### Aircraft Capacity Logic

For the operational Slot Schedule dashboard, the established overall aircraft-demand rule is:

```text
Aircraft Demand = Arrival + Departure + OPC
```

Example:

```text
Arrival   = 7
Departure = 6
OPC       = 2

Aircraft Demand = 15

NAC = 6

15 / 6 A/C
250%

Status = OVER CAPACITY
```

OPC represents aircraft occupying parking stands, such as RON aircraft, and is not treated as a normal flight movement for the Total Movements figure.

---

# 📊 DAU Analytics

DAU dashboards are designed around the actual meaning and structure of each report instead of rendering every report as the same generic table.

## DAU1 — Arus Lalu Lintas

**Purpose:** Flight and traffic-volume analysis.

### Recommended dashboard

- Total aircraft movement
- Arrival / Departure
- Passenger
- Baggage
- Cargo
- POS
- Origin / Destination analysis
- Flight-level detail table

### Main visualization

**Combo Chart**

- Grouped bars → Aircraft Arrival vs Departure
- Line → Total Passenger

Additional cargo/baggage composition visualization can be shown when compatible source data exists.

### Filters

- Direction
- Airport / route
- Traffic category where the source supports it
- Search by IATA / city / airport

---

## DAU2 — Secara Total

**Purpose:** Domestic vs International comparison.

### Main visualization

**100% Stacked Horizontal Bar**

Metrics may include:

- Aircraft
- Passenger
- Baggage
- Cargo

### Filters

- Metric
- Absolute / Percentage display

---

## DAU3 — Status Penerbangan

**Purpose:** Analyze flight status/classification.

### Main visualization

**Nested Donut / Status Distribution**

The actual categories must come from the source data. The dashboard must not fabricate categories that are not available in the source or a valid enrichment source.

### Filters

- Status / category
- Arrival / Departure
- Flight type where supported

---

## DAU4 — Asal/Tujuan

**Purpose:** Origin and destination analysis.

### Main visualization

**Bi-Directional Diverging Bar**

- Left → Top Origin / Arrival
- Right → Top Destination / Departure

### Filters

- Top 5 / 10 / 20
- Aircraft / Passenger
- Direction
- Airport search
- DOM / INT where supported

---

## DAU4A — Operator × Airport

**Purpose:** Analyze operator relationships with airports.

### Main visualization

**Hierarchical Treemap** or nested bars.

Hierarchy:

```text
Operator
   ↓
Airport
   ↓
Traffic Volume
```

### Filters

- Operator
- Airport
- Region / city where available
- DOM / INT where supported

---

## DAU4B — Airport × Airline / Operator

**Purpose:** Matrix analysis between airports and airlines/operators.

### Main visualization

**Matrix Heatmap**

- Rows → Airport
- Columns → Airline / Operator
- Cell intensity → flight frequency / traffic volume

### Filters

- Minimum flight threshold
- DOM / INT
- Airport search
- Airline search

A matrix cell can be used for drill-down into the underlying airport/operator records.

---

## DAU5 — Airline Operator

**Purpose:** Airline performance and distribution.

### Main visualization

**Pareto Chart**

- Bars → Airline traffic volume
- Cumulative line → percentage

### Ranking metrics

- Aircraft
- Passenger
- Baggage
- Cargo
- POS where supported

### Filters

- Ranking metric
- Airline
- Direction
- DOM / INT where supported

---

## DAU5A — Airline Operator (A)

**Purpose:** Airline and Extra Crew analysis.

### Main visualization

**Grouped Horizontal Bar**

Compare available source metrics such as:

- Operating Crew
- Extra Crew
- Arrival
- Departure
- Total

### Filters

- Airline
- ARR
- DEP
- TOTAL

---

## DAU5B — Airline Operator (B)

**Purpose:** Terminal × Airline analysis.

### Main visualization

**Stacked Bar by Terminal**

- X-axis → Terminal
- Stacks → Airline
- Height → traffic volume

### Filters

- Terminal
- Airline where supported

Example:

```text
Terminal 2F
```

The KPI, charts and detail table must update to the selected terminal only.

---

## DAU5C — Airline Operator (C)

**Purpose:** Airline profile analysis.

The dashboard should first inspect the actual DAU5C schema and use only supported fields.

Where source data supports both Seat Capacity and Passenger, the application can calculate:

```text
Load Factor = Passenger / Seat Capacity × 100
```

If those fields are not available, the dashboard must not fabricate Load Factor.

---

# 🛫 DAU6 — Tipe Pesawat

**Purpose:** Aircraft fleet mix analysis.

### Main visualization

**Aircraft Type Ranking**

Example categories can include actual types such as:

- A320
- B737-800
- ATR72
- B777

The dashboard may also provide fleet-category or WTC analysis only when the necessary source/master data exists.

### Filters

- Aircraft Type
- Aircraft category where available
- WTC where available
- Sort by Aircraft / Passenger

---

# ⏱️ DAU10 — Jam Puncak Pesawat/Penumpang

**Purpose:** Hourly peak analysis.

### KPI

- Peak Aircraft
- Peak Passenger
- Peak Hour
- Total Aircraft
- Total Passenger

### Main visualizations

1. Hourly Aircraft Movement
2. Hourly Passenger Movement
3. Peak Hour Analysis

### Filters

- Date
- Flight type
- Hour
- Terminal where supported
- Metric

---

# 🏢 DAU10A — Jam Puncak Menurut Terminal

DAU10A focuses on **Time × Terminal** analysis.

It provides two complementary views.

## View 1 — TIME × TERMINAL HEATMAP

Rows:

```text
Terminal
```

Columns:

```text
Hour
```

Metric can be switched between available source metrics such as:

- Aircraft
- Passenger
- Crew
- Baggage
- Cargo
- POS

---

## View 2 — DISTRIBUSI PER JAM

The Distribution Per Hour view provides an airport-wide or selected-terminal operational capacity analysis.

### Terminal filter

```text
Terminal:
[ ALL TERMINALS ▼ ]
```

When a terminal such as `2F` is selected:

- KPI uses Terminal 2F
- Chart uses Terminal 2F
- Tooltip uses Terminal 2F
- Table uses Terminal 2F
- PDF uses Terminal 2F

All values must come from the same filtered dataset.

---

## DAU10A Operational Capacity Envelope

The Distribution Per Hour chart uses a two-direction operational layout:

```text
                 ARRIVAL CAPACITY
                        +8
            ╔══════════════════════════╗
            ║       ARRIVAL ↑          ║
            ║        ███               ║
06:00 ───── ║────── TIME / HOUR ──────║ ───── 21:00
            ║        ███               ║
            ║      DEPARTURE ↓         ║
            ╚══════════════════════════╝
                        -8
                DEPARTURE CAPACITY
```

The envelope is one connected dashed rectangle:

- **Top** → Arrival Capacity
- **Bottom** → Departure Capacity
- **Left** → Operating Hours Start
- **Right** → Operating Hours End

Arrival and Departure capacities remain independently editable.

### Directional status rules

Arrival:

```text
ARR < ARR CAP  → no badge / Available
ARR = ARR CAP  → MAX
ARR > ARR CAP  → OVER
```

Departure:

```text
DEP < DEP CAP  → no badge / Available
DEP = DEP CAP  → MAX
DEP > DEP CAP  → OVER
```

Arrival status must never affect Departure status, and Departure status must never affect Arrival status.

Example:

```text
ARR CAP = 30
DEP CAP = 30

ARR = 30
DEP = 45

ARR → 30/30 → MAX
DEP → 45/30 → OVER
```

Available bars do not display a badge.

### Cursor tooltip

When hovering over a bar, SlotWaves displays a small cursor-following tooltip containing the actual generated value, the current capacity, scope, hour and status.

Example:

```text
08:00–08:59 (WIB)
🟠 ARRIVAL

Aircraft
31 / 8 A/C

Scope
ALL TERMINALS

OVER CAPACITY
```

The tooltip is rendered above the chart so it is not hidden behind bars or clipped by the chart container.

---

# 🧭 DAU10B — Jam Puncak Pesawat/Penumpang (Block On/Off)

**Purpose:** Block On / Block Off analysis where the actual DAU10B source provides those dimensions.

### Main visualization

- Block On vs Block Off hourly comparison
- Hourly aircraft/passenger distribution where supported

### Filters

- Block operation
- Terminal
- Hour
- Flight type
- Metric

The dashboard must inspect the real DAU10B schema and must not invent Block On/Off values that are not present in the source.

---

# 📈 DAU11 — Data Statistik 1

**Purpose:** Aggregate ARR/DEP and DOM/INT statistics.

Where the source contains enough relationships, the dashboard can use a **Sankey Flow** to show passenger/traffic flow between categories such as:

```text
TOTAL
  ↓
DOM / INT
  ↓
ARR / DEP
  ↓
DIRECT / TRANSIT / TRANSFER
```

The exact flow is limited to relationships actually supported by the source.

### Filters

- Arrival / Departure
- Passenger type where available
- Domestic / International

---

# 📊 DAU12 — Data Statistik 2

**Purpose:** ARR/DEP × DOM/INT comparison.

### Main visualization

**Grouped Column Chart**

```text
ARRIVAL
 ├── DOM
 └── INT

DEPARTURE
 ├── DOM
 └── INT
```

### Metric toggle

- Aircraft
- Passenger

### Display mode

- Absolute
- Percentage

### Summary table

```text
               DOM      INT      TOTAL
ARRIVAL
DEPARTURE
```

Aircraft and Passenger metrics must remain separate.

---

# 🔎 Interactive Analytics

SlotWaves uses a reusable filtering approach so that the dashboard remains synchronized.

Conceptually:

```text
reportData
    ↓
selectedFilters
    ↓
filteredData
    ├── KPI
    ├── Charts
    ├── Tables
    ├── Tooltips
    └── PDF
```

Changing a filter should not create a second inconsistent dataset.

Supported filters vary by report and can include:

- Date
- Flight Type
- Direction
- Airport
- Origin
- Destination
- Airline
- Terminal
- Aircraft Type
- Hour
- Metric
- Status

---

# 📤 Export System

Generated reports can be exported according to the report type.

### PDF

PDF reports include, where applicable:

- Report title
- DAU number
- Airport
- Date range
- Flight scope
- Terminal scope
- KPI summary
- Charts / diagrams
- Detail tables
- Active filters
- Generated timestamp
- Page numbering

For filtered dashboards, the PDF must represent the same filtered dataset shown on screen.

### Excel / CSV

Export is available where supported by the report and existing implementation.

---

# 🗄️ Database

SlotWaves uses PostgreSQL through Supabase for production data.

The application is designed around a non-destructive database approach.

Existing operational tables remain available for data such as:

```text
airlines
airports
cache
cache_locks
exports
flight_pairings
flights
migrations
sessions
timeline_positions
timeline_settings
uploads
```

The `uploads` record can associate a generated report with its report type and normalized report data.

The project must not reset or recreate the whole production database just to add DAU functionality.

---

# 🏗️ Application Architecture

Simplified architecture:

```text
                    ┌───────────────────────────┐
                    │          HOME             │
                    │ Select Type to Generate   │
                    └─────────────┬─────────────┘
                                  │
                     ┌────────────┴────────────┐
                     │                         │
                     ▼                         ▼
            Airport Slot Schedule             DAU
                     │                         │
                    PDF                  DAU1–DAU12
                     │                         │
                     ▼                         ▼
                 Validator                 Validator
                     │                         │
                     ▼                         ▼
                  Parser                    Parser
                     │                         │
                     ▼                         ▼
                 Timeline                 Normalize
                     │                         │
                     ▼                         ▼
                 Capacity                 Aggregate
                     │                         │
                     └────────────┬────────────┘
                                  ▼
                              DASHBOARD
                                  │
                           ┌──────┴──────┐
                           ▼             ▼
                        FILTER        ANALYZE
                           │             │
                           └──────┬──────┘
                                  ▼
                               EXPORT
```

---

# 📁 Project Structure

The project follows a modular Laravel architecture.

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

## `DauDashboardController.php`

Controls DAU dashboard/report flow and connects parsed report data to dashboard views and exports.

## `TemplateValidator.php`

Validates uploaded files against the selected report type and its expected structure.

## `ReportTemplateRegistry.php`

Central registry for report types, templates, validation rules, parsers, dashboards, and export behavior.

## `app/Services/Dau/Parsers/`

Contains report-specific parsers. Each DAU parser understands its own source layout instead of forcing every source into a generic spreadsheet schema.

---

# 🛠️ Technology Stack

### Backend

- PHP
- Laravel
- Laravel Blade
- Laravel Breeze where authentication scaffolding is used

### Frontend

- Blade
- Tailwind CSS
- Alpine.js
- Vite
- JavaScript

### Database

- PostgreSQL
- Supabase

### Deployment

- Vercel

### Reporting / Data Processing

- Excel template parsing for DAU reports
- PDF ingestion for Airport Slot Schedule
- PDF / Excel / CSV export according to report type

---

# 🔐 Data Integrity Principles

SlotWaves follows several rules to keep generated reports trustworthy.

### 1. Source data is authoritative

Dashboard metrics must come from the actual uploaded source or a deterministic enrichment source.

### 2. No fabricated values

If a metric is absent from the source and cannot be legitimately derived, the dashboard should show `N/A` or omit that metric.

### 3. Filter consistency

The same filtered dataset must feed KPI, charts, tables, tooltips, and exports.

### 4. No parser cross-contamination

A DAU10 parser should not silently replace the structure of DAU10A, and a DAU report must not be parsed as Airport Slot Schedule.

### 5. Existing operational logic is preserved

The addition of DAU analytics must not break the existing Airport Slot Schedule workflow.

---

# 🧪 Testing

The project includes validation for parser behavior, capacity logic, dashboard calculations, and production-oriented regressions.

The development workflow should include:

```bash
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test
npm ci
npm run build
```

Where project-specific verification scripts exist, run them as part of the regression process.

Important regression areas:

- Airport Slot Schedule PDF ingestion
- Arrival / Departure parsing
- Flight pairing
- RON
- OPC
- Aircraft Capacity / NAC
- Operating Hours
- LOCAL / UTC
- DAU template validation
- DAU1–DAU12 parsing
- Filters
- Dashboard totals
- PDF export

---

# 🚀 Local Development

## Requirements

Recommended development environment:

- PHP 8.x compatible with the project's Laravel version
- Composer
- Node.js / npm
- PostgreSQL-compatible database or Supabase
- Git

## Installation

```bash
git clone https://github.com/ValhallaDemon356/SlotWaves.git
cd SlotWaves
```

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm ci
```

Create environment file:

```bash
cp .env.example .env
```

Generate application key if needed:

```bash
php artisan key:generate
```

Configure the database through environment variables.

Run migrations:

```bash
php artisan migrate
```

Build frontend assets:

```bash
npm run build
```

Run the application:

```bash
php artisan serve
```

In a separate terminal, use the project's preferred Vite development workflow when actively developing frontend assets.

---

# ☁️ Production Architecture

The production environment is designed around:

```text
GitHub
   ↓
Vercel
   ↓
Laravel Application
   ↓
Supabase PostgreSQL
```

The application should keep secrets in environment variables instead of committing them to Git.

Never commit:

```text
.env
.env.production
.env.local
```

or any credential such as:

- database passwords
- Supabase service-role keys
- Supabase secret keys
- JWT secrets
- Vercel tokens
- GitHub tokens
- application secrets

---

# 📦 Deployment Checklist

Before production deployment:

```text
[ ] Environment variables configured
[ ] Database connection verified
[ ] Migrations verified
[ ] Composer dependencies installed
[ ] npm dependencies installed
[ ] Vite production build succeeds
[ ] Routes verified
[ ] DAU template validation tested
[ ] Airport Slot Schedule regression tested
[ ] PDF export tested
[ ] Master Data tested
[ ] No secrets committed
[ ] GitHub main updated
[ ] Vercel deployment completed
[ ] Production smoke test completed
```

---

# 🛡️ Non-Destructive Database Policy

SlotWaves should use migrations for schema evolution.

Do not perform production operations such as:

```text
DROP DATABASE
DROP ALL TABLES
TRUNCATE production data
Recreate the entire Supabase project
```

unless explicitly required and separately approved.

The DAU feature is intended to extend the existing system rather than replace the operational database.

---

# 🔭 Roadmap

Potential future improvements include:

- More DAU analytics and drill-down views
- Advanced terminal analytics
- Historical report comparison
- Scheduled report generation
- Report versioning
- Role-based access control
- Audit trail for report changes
- More advanced airport capacity analytics
- More export formats
- Additional OASYS report templates

---

# 🤝 Development Principles

SlotWaves is built around the following principles:

### Accuracy over decoration

A chart is useful only when its values can be traced back to the source data.

### Report-specific analytics

Different DAU reports represent different operational questions and therefore need different visualizations.

### Preserve working functionality

New functionality should extend the existing Airport Slot Schedule and DAU systems without unnecessarily rewriting stable logic.

### One source of truth

The report type, normalized data, filter state, dashboard, and exported report must remain synchronized.

---

# 📄 License

Add the project's intended license here before publishing a formal open-source release.

---

# 👨‍💻 Project

**SlotWaves**  
Airport Operational Slot & Flight Intelligence

Repository: `ValhallaDemon356/SlotWaves`

---

> **SlotWaves transforms operational flight data into readable, interactive, and report-ready airport intelligence.**
