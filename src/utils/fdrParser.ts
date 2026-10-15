/**
 * SlotWaves: Comprehensive Flight Daily Report (FDR) OASYS Engine
 *
 * Implements deterministic auto-detection, pre-scan staging, and dedicated parsers
 * for all 7 InJourney Airports OASYS FDR report templates:
 * 1. NORMAL (33 Columns)
 * 2. EX_HALIM (33 Columns - Halim Monitoring)
 * 3. LOAD_FACTOR (13 Columns)
 * 4. COMPARE_LOAD_FACTOR (20 Columns YoY)
 * 5. COMPARE_LOAD_FACTOR_DAY (20 Columns YoY Day-by-Day)
 * 6. OASYS_VS_APPS (16 Columns + Nested Matrix)
 * 7. OASYS_VS_EDIFLY (14 Columns)
 *
 * Plus critical bug fixes:
 * - Operating Crew (CRW + EX. CRW)
 * - Cross-midnight delay outliers & >360m filter toggle
 * - REAL = NO (Cancelled / Hold Flights: 30-11--0001, *-HOLD, nan%)
 * - Suffix dictionary & Leg subtype extraction
 * - Airline operator code normalization
 * - Unused columns unlock (MTOW, POS, Umroh, FINAL)
 * - Route analytics & 35 InJourney Airports coordinates
 */

export type OasysFdrType =
  | 'NORMAL'
  | 'EX_HALIM'
  | 'LOAD_FACTOR'
  | 'COMPARE_LOAD_FACTOR'
  | 'COMPARE_LOAD_FACTOR_DAY'
  | 'OASYS_VS_APPS'
  | 'OASYS_VS_EDIFLY';

export const OASYS_FDR_TYPE_CONFIG: Record<
  OasysFdrType,
  {
    name: string;
    label: string;
    columnsCount: number;
    badgeColor: string;
    description: string;
  }
> = {
  NORMAL: {
    name: 'Normal FDR',
    label: 'NORMAL FDR (33 COLUMNS)',
    columnsCount: 33,
    badgeColor: 'bg-blue-100 text-blue-800 border-blue-300',
    description: 'Standard operational daily report with 33 columns telemetry.',
  },
  EX_HALIM: {
    name: 'Ex Halim (CGK)',
    label: 'EX HALIM (CGK) MONITORING (33 COLUMNS)',
    columnsCount: 33,
    badgeColor: 'bg-purple-100 text-purple-800 border-purple-300',
    description: 'Halim Perdanakusuma commercial flight transfer monitoring at CGK.',
  },
  LOAD_FACTOR: {
    name: 'Load Factor Report',
    label: 'LOAD FACTOR (13 COLUMNS)',
    columnsCount: 13,
    badgeColor: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    description: 'Seat load factor report with capacity, commercial pax & SLF %.',
  },
  COMPARE_LOAD_FACTOR: {
    name: 'Compare Load Factor YoY',
    label: 'COMPARE LOAD FACTOR YoY (20 COLUMNS)',
    columnsCount: 20,
    badgeColor: 'bg-amber-100 text-amber-800 border-amber-300',
    description: 'Year-over-Year comparison of Flights, Pax, Cargo, SeatCap, and SLF %.',
  },
  COMPARE_LOAD_FACTOR_DAY: {
    name: 'Compare Load Factor Daily YoY',
    label: 'COMPARE LOAD FACTOR DAILY YoY (20 COLUMNS)',
    columnsCount: 20,
    badgeColor: 'bg-orange-100 text-orange-800 border-orange-300',
    description: 'Day-by-Day Year-over-Year comparison of operational traffic and SLF %.',
  },
  OASYS_VS_APPS: {
    name: 'OASYS vs APPS Audit',
    label: 'OASYS VS APPS AUDIT (16 COLUMNS)',
    columnsCount: 16,
    badgeColor: 'bg-rose-100 text-rose-800 border-rose-300',
    description: 'Departure flight clearance audit against APPS passenger clearance.',
  },
  OASYS_VS_EDIFLY: {
    name: 'OASYS vs EDIFLY Integration',
    label: 'OASYS VS EDIFLY (14 COLUMNS)',
    columnsCount: 14,
    badgeColor: 'bg-cyan-100 text-cyan-800 border-cyan-300',
    description: 'EDIFLY API Pax & Cargo telecommunication integration audit.',
  },
};

/**
 * Official OASYS Flight Suffix Dictionary.
 */
export const OASYS_SUFFIX_DICTIONARY: Record<
  string,
  { code: string; label: string; description: string; category: string }
> = {
  X: { code: 'X', label: 'Extra Flight', description: 'Additional unscheduled movement', category: 'Additional' },
  R: { code: 'R', label: 'Return to Airport/Base (RTB/RTA)', description: 'Flight returned to departure station or base', category: 'Irregular' },
  I: { code: 'I', label: 'Haji Flight', description: 'Pilgrimage charter movement', category: 'Special' },
  D: { code: 'D', label: 'Divert', description: 'Flight diverted to alternate station', category: 'Irregular' },
  A: { code: 'A', label: 'Hanggar / Reschedule 4 Jam', description: 'Maintenance hangar movement or rescheduling >= 4h', category: 'Technical' },
  F: { code: 'F', label: 'Ferry Flight', description: 'Aircraft repositioning without commercial payload', category: 'Operational' },
  H: { code: 'H', label: 'Charter Cargo/Mail', description: 'Dedicated cargo charter flight', category: 'Charter' },
  O: { code: 'O', label: 'Charter Special Handling', description: 'Special handling charter flight', category: 'Charter' },
  L: { code: 'L', label: 'Military Flight', description: 'Indonesian military transport or exercise', category: 'State/Military' },
  '&': { code: '&', label: 'Military Flight', description: 'Military / Special operations', category: 'State/Military' },
  G: { code: 'G', label: 'Government Flight', description: 'State official or government mission', category: 'State/Military' },
  V: { code: 'V', label: 'VIP Flight', description: 'Head of state / VVIP movement', category: 'State/Military' },
  M: { code: 'M', label: 'Medical Flight', description: 'Medevac or air ambulance movement', category: 'Emergency' },
  N: { code: 'N', label: 'Next Day / Postpone', description: 'Flight postponed to next operating day', category: 'Irregular' },
  Z: { code: 'Z', label: 'Extend', description: 'Operational slot extension', category: 'Operational' },
  S: { code: 'S', label: 'Flying School / Training', description: 'Flight crew pilot training', category: 'Training' },
  T: { code: 'T', label: 'Flying School / Training', description: 'Technical flight test', category: 'Training' },
  K: { code: 'K', label: 'Flying School / Training', description: 'Cadet training movement', category: 'Training' },
};

/**
 * 35 InJourney Airports Coordinates Dictionary.
 */
export const INJOURNEY_AIRPORTS_COORDINATES: Record<
  string,
  { name: string; city: string; lat: number; lng: number }
> = {
  CGK: { name: 'Soekarno-Hatta International Airport', city: 'Jakarta', lat: -6.1256, lng: 106.6559 },
  DPS: { name: 'I Gusti Ngurah Rai International Airport', city: 'Denpasar / Bali', lat: -8.7482, lng: 115.1672 },
  SUB: { name: 'Juanda International Airport', city: 'Surabaya', lat: -7.3798, lng: 112.7876 },
  KNO: { name: 'Kualanamu International Airport', city: 'Medan', lat: 3.6422, lng: 98.8853 },
  YIA: { name: 'Yogyakarta International Airport', city: 'Kulon Progo', lat: -7.9073, lng: 110.0544 },
  UPG: { name: 'Sultan Hasanuddin International Airport', city: 'Makassar', lat: -5.0616, lng: 119.554 },
  BPN: { name: 'Sultan Aji Muhammad Sulaiman Sepinggan', city: 'Balikpapan', lat: -1.2683, lng: 116.8944 },
  BDJ: { name: 'Syamsudin Noor International Airport', city: 'Banjarmasin', lat: -3.4475, lng: 114.7617 },
  SRG: { name: 'Jenderal Ahmad Yani International Airport', city: 'Semarang', lat: -6.9744, lng: 110.3744 },
  PLM: { name: 'Sultan Mahmud Badaruddin II Airport', city: 'Palembang', lat: -2.8986, lng: 104.7003 },
  PNK: { name: 'Supadio International Airport', city: 'Pontianak', lat: -0.1506, lng: 109.4039 },
  PKU: { name: 'Sultan Syarif Kasim II International Airport', city: 'Pekanbaru', lat: 0.4608, lng: 101.4444 },
  PDG: { name: 'Minangkabau International Airport', city: 'Padang', lat: -0.7875, lng: 100.2808 },
  BTH: { name: 'Hang Nadim International Airport', city: 'Batam', lat: 1.1211, lng: 104.1189 },
  TNJ: { name: 'Raja Haji Fisabilillah International Airport', city: 'Tanjung Pinang', lat: 0.9236, lng: 104.5317 },
  BDO: { name: 'Husein Sastranegara International Airport', city: 'Bandung', lat: -6.9006, lng: 107.5761 },
  HLP: { name: 'Halim Perdanakusuma International Airport', city: 'Jakarta', lat: -6.2656, lng: 106.8906 },
  KJT: { name: 'Kertajati International Airport', city: 'Majalengka', lat: -6.6578, lng: 108.1889 },
  LOP: { name: 'Lombok International Airport', city: 'Lombok Praya', lat: -8.7583, lng: 116.2764 },
  MDC: { name: 'Sam Ratulangi International Airport', city: 'Manado', lat: 1.5494, lng: 124.9264 },
  AMQ: { name: 'Pattimura Airport', city: 'Ambon', lat: -3.7103, lng: 128.0894 },
  KOE: { name: 'El Tari Airport', city: 'Kupang', lat: -10.1714, lng: 123.6708 },
  PGK: { name: 'Depati Amir Airport', city: 'Pangkal Pinang', lat: -2.1628, lng: 106.1394 },
  TJQ: { name: 'H.A.S. Hanandjoeddin Airport', city: 'Tanjung Pandan / Belitung', lat: -2.7606, lng: 107.755 },
  TKG: { name: 'Radin Inten II Airport', city: 'Bandar Lampung', lat: -5.2422, lng: 105.1786 },
  DJB: { name: 'Sultan Thaha Airport', city: 'Jambi', lat: -1.6381, lng: 103.6444 },
  BKS: { name: 'Fatmawati Soekarno Airport', city: 'Bengkulu', lat: -3.8647, lng: 102.34 },
  BTJ: { name: 'Sultan Iskandar Muda International Airport', city: 'Banda Aceh', lat: 5.5236, lng: 95.4206 },
  DTB: { name: 'Raja Sisingamangaraja XII Airport (Silangit)', city: 'Siborong-borong', lat: 2.2575, lng: 98.9936 },
  BWX: { name: 'Banyuwangi International Airport', city: 'Banyuwangi', lat: -8.3106, lng: 114.34 },
  JOG: { name: 'Adisutjipto International Airport', city: 'Yogyakarta', lat: -7.7881, lng: 110.4317 },
  PKY: { name: 'Tjilik Riwut Airport', city: 'Palangkaraya', lat: -2.2256, lng: 113.9439 },
  BIK: { name: 'Frans Kaisiepo International Airport', city: 'Biak', lat: -1.1906, lng: 136.1103 },
  DJJ: { name: 'Sentani International Airport', city: 'Jayapura', lat: -2.5769, lng: 140.5164 },
  PWL: { name: 'Tunggul Wulung Airport', city: 'Cilacap / Purwokerto', lat: -7.6436, lng: 109.0208 },
  PCB: { name: 'Pondok Cabe Airport', city: 'Tangerang Selatan', lat: -6.3386, lng: 106.7533 },
};

/**
 * Pre-Scan Extraction Metadata Interface.
 */
export interface FDRPreScanMetadata {
  reportType: OasysFdrType;
  reportTypeLabel: string;
  reportTypeBadgeColor: string;
  branchCode: string;
  transactionsDateFdr: string;
  operator: string;
  realization: string;
  suffix: string;
  leg: string;
  categoryCode: string;
  totalRows: number;
  isExHalim: boolean;
  fileName: string;
  fileSize: number;
}

export interface NormalizedFDRRecord {
  flightDate: string;
  airline: string;
  airlineRaw: string;
  airlineCode: string;
  flightNumber: string;
  flightNoBase: string;
  flightSuffix: string;
  pairedNo: string;
  aircraftReg: string;
  aircraftType: string;
  movementType: 'Arrival' | 'Departure';
  legRaw: string;
  legSubtype: 'SCHED' | 'UNSCHED' | 'CARGO' | 'OTHER';
  origin: string;
  destination: string;
  scheduledTime: string;
  actualTime: string;
  scheduledHour: number;
  actualHour: number;
  delayMinutes: number;
  rawDelayMinutes: number;
  status: 'ON_TIME' | 'DELAYED' | 'EARLY' | 'CANCELLED' | 'HOLD_OR_CANCELLED';
  realized: boolean;
  mtowTons: number;
  capacity: number;
  paxLoad: number;
  loadFactorPercent: number;
  adult: number;
  child: number;
  infant: number;
  transit: number;
  transfer: number;
  divert: number;
  missed: number;
  operatingCrew: number;
  extraCrew: number;
  totalCrew: number;
  umrohPax: number;
  cargoKg: number;
  baggageKg: number;
  posKg: number;
  totalPayloadKg: number;
  stand: string;
  runway: string;
  isFinal: boolean;
  finalTime: string;
  branch: string;
}

export interface FDRHeaderMapping {
  headerRowIndex: number;
  columns: {
    flightDate?: number;
    airline?: number;
    flightNumber?: number;
    aircraftReg?: number;
    aircraftType?: number;
    movementType?: number;
    origin?: number;
    destination?: number;
    scheduledTime?: number;
    actualTime?: number;
    sibt?: number;
    sobt?: number;
    aibt?: number;
    aobt?: number;
    sibtSobt?: number;
    aibtAobt?: number;
  };
}

export interface HourlySlotWavesBucket {
  hour: number;
  hourLabel: string;
  timeRange: string;
  totalMovements: number;
  arrivals: number;
  departures: number;
  onTime: number;
  delayed: number;
  early: number;
  cancelled: number;
  airlines: Record<string, number>;
}

export interface HourlySlotWavesSummary {
  buckets: HourlySlotWavesBucket[];
  totalMovements: number;
  totalArrivals: number;
  totalDepartures: number;
  totalOnTime: number;
  totalDelayed: number;
  totalEarly: number;
  totalCancelled: number;
  peakHour: {
    hour: number;
    timeRange: string;
    movements: number;
  };
  otpPercentage: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// STRING / NUMBER CLEANING & INDONESIAN DECIMAL FORMATTING
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Clean HTML string content, removing tags and decoding standard entities.
 */
export function cleanHtmlText(text: string): string {
  if (!text) return '';
  return text
    .replace(/<br\s*\/?>/gi, ' ')
    .replace(/<[^>]+>/g, '')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#39;/gi, "'")
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Parse Indonesian decimal formatting where '.' is thousand separator and ',' is decimal separator.
 * e.g. "1.242,86" -> 1242.86
 * e.g. "-33,33" -> -33.33
 * e.g. "71.8%" -> 71.8
 */
export function parseIndonesianNumber(val: unknown, fallback = 0): number {
  if (val === null || val === undefined) return fallback;
  if (typeof val === 'number') return isNaN(val) ? fallback : val;
  const str = cleanHtmlText(String(val)).replace(/%/g, '').trim();
  if (str === '' || str === '-' || str.toLowerCase() === 'nan' || str.toLowerCase() === 'null') return fallback;

  // Check if comma is decimal separator (e.g. 1.242,86 or 33,33)
  if (str.includes(',')) {
    const cleaned = str.replace(/\./g, '').replace(',', '.');
    const num = parseFloat(cleaned);
    return isNaN(num) ? fallback : num;
  }

  // Standard notation (e.g. 1242.86 or 1242)
  const num = parseFloat(str);
  return isNaN(num) ? fallback : num;
}

/**
 * Convert Excel serial decimal time (e.g. 0.354166667) or string time ("08:30", "08:30:00")
 * to standard "HH:mm" and extract integer hour (0–23).
 */
export function parseExcelTime(val: unknown): { timeStr: string; hour: number } | null {
  if (val === null || val === undefined) return null;
  const str = cleanHtmlText(String(val)).trim();
  if (
    str === '' ||
    str === '-' ||
    str === 'N/A' ||
    str === 'NULL' ||
    str.includes('30-11--0001') ||
    str.includes('0000-00-00')
  ) {
    return null;
  }

  // 1. Check for standard "HH:mm(:ss)" or datetime string with "HH:mm"
  const timeMatch = str.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (timeMatch) {
    let h = parseInt(timeMatch[1], 10);
    const m = parseInt(timeMatch[2], 10);
    if (h >= 24) h = 23;
    const timeStr = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    return { timeStr, hour: h };
  }

  // 2. Check for 4-digit military time (e.g. "0615" -> "06:15")
  const militaryMatch = str.match(/^(\d{2})(\d{2})$/);
  if (militaryMatch) {
    const h = parseInt(militaryMatch[1], 10);
    const m = parseInt(militaryMatch[2], 10);
    if (h < 24 && m < 60) {
      return { timeStr: `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`, hour: h };
    }
  }

  // 3. Check for numeric value (Excel decimal time, e.g. 0.354166667 or 45200.354166667)
  const isDecimal =
    typeof val === 'number' ||
    (!isNaN(Number(str)) && str.includes('.') && !str.includes(':') && !str.includes('-') && !str.includes('/'));
  if (isDecimal) {
    const num = typeof val === 'number' ? val : parseFloat(str);
    if (!isNaN(num) && num >= 0) {
      let fraction = num % 1;
      if (fraction === 0 && num > 0 && num < 1) fraction = num;
      if (fraction === 0 && num >= 1) fraction = 0;

      const totalSeconds = Math.round(fraction * 86400);
      const h = Math.floor(totalSeconds / 3600) % 24;
      const m = Math.floor((totalSeconds % 3600) / 60);
      const timeStr = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
      return { timeStr, hour: h };
    }
  }

  return null;
}

/**
 * Standardize date values from multiple OASYS variants into "YYYY-MM-DD".
 */
export function parseExcelDate(val: unknown, fallbackDate?: string): string {
  if (val === null || val === undefined) return fallbackDate || new Date().toISOString().slice(0, 10);
  const str = cleanHtmlText(String(val)).trim();
  if (str === '' || str === '-' || str === 'N/A' || str.includes('30-11--0001')) {
    return fallbackDate || new Date().toISOString().slice(0, 10);
  }

  // 1. YYYY-MM-DD or YYYY/MM/DD
  const isoMatch = str.match(/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/);
  if (isoMatch) {
    return `${isoMatch[1]}-${isoMatch[2].padStart(2, '0')}-${isoMatch[3].padStart(2, '0')}`;
  }

  // 2. DD-MM-YYYY or DD/MM/YYYY
  const idMatch = str.match(/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/);
  if (idMatch) {
    return `${idMatch[3]}-${idMatch[2].padStart(2, '0')}-${idMatch[1].padStart(2, '0')}`;
  }

  // 3. Excel serial date integer (e.g. 45200)
  const num = typeof val === 'number' ? val : parseFloat(str);
  if (!isNaN(num) && num > 30000 && num < 60000) {
    const epoch = new Date(1899, 11, 30);
    const dateObj = new Date(epoch.getTime() + Math.floor(num) * 86400000);
    const y = dateObj.getFullYear();
    const m = String(dateObj.getMonth() + 1).padStart(2, '0');
    const d = String(dateObj.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  return fallbackDate || new Date().toISOString().slice(0, 10);
}

/**
 * Calculate delay in minutes between actualTime and scheduledTime with cross-midnight support.
 */
export function calculateDelayMinutes(schedTimeStr: string, actTimeStr: string): number {
  if (!schedTimeStr || !actTimeStr) return 0;
  const sParts = schedTimeStr.split(':').map((x) => parseInt(x, 10));
  const aParts = actTimeStr.split(':').map((x) => parseInt(x, 10));

  if (sParts.length < 2 || aParts.length < 2) return 0;

  const sTotalMin = sParts[0] * 60 + sParts[1];
  const aTotalMin = aParts[0] * 60 + aParts[1];

  let diff = aTotalMin - sTotalMin;

  // Cross-midnight adjustment (e.g. scheduled 23:50, actual 00:10 -> diff +20 instead of -1420)
  if (diff < -720) {
    diff += 1440;
  } else if (diff > 720) {
    diff -= 1440;
  }

  return diff;
}

/**
 * Determine flight movement direction (Arrival vs Departure).
 */
export function resolveMovementType(
  origin: string,
  destination: string,
  legValue?: string,
  sibt?: string,
  sobt?: string,
  baseAirport = 'CGK'
): 'Arrival' | 'Departure' {
  const normBase = baseAirport.toUpperCase().trim();
  const normOrig = origin.toUpperCase().trim();
  const normDest = destination.toUpperCase().trim();

  // 1. Direct airport relative check
  if (normDest === normBase && normOrig !== normBase) {
    return 'Arrival';
  }
  if (normOrig === normBase && normDest !== normBase) {
    return 'Departure';
  }

  // 2. Leg column check (A / D / ARR / DEP)
  if (legValue) {
    const legUpper = legValue.toUpperCase().trim();
    if (legUpper.startsWith('A') || legUpper.includes('ARR')) return 'Arrival';
    if (legUpper.startsWith('D') || legUpper.includes('DEP')) return 'Departure';
  }

  // 3. SIBT vs SOBT presence
  if (sibt && !sobt) return 'Arrival';
  if (sobt && !sibt) return 'Departure';

  return 'Departure';
}

// ─────────────────────────────────────────────────────────────────────────────
// FLIGHT SUFFIX & AIRLINE NORMALIZATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Extract trailing suffix from flight number using regex ^(\d+)([A-Z<$&^])?$.
 */
export function extractFlightSuffix(flightNo: string): {
  base: string;
  suffix: string;
  suffixInfo: (typeof OASYS_SUFFIX_DICTIONARY)[string] | null;
} {
  const clean = cleanHtmlText(String(flightNo || '')).toUpperCase();
  const match = clean.match(/^([A-Z0-9]*?)(\d+)([A-Z<$&^])?$/);
  if (match && match[3]) {
    const base = `${match[1] || ''}${match[2]}`;
    const suffix = match[3];
    return {
      base,
      suffix,
      suffixInfo: OASYS_SUFFIX_DICTIONARY[suffix] || {
        code: suffix,
        label: `Suffix ${suffix}`,
        description: 'Special Flight Suffix',
        category: 'Custom',
      },
    };
  }
  return { base: clean, suffix: '', suffixInfo: null };
}

export interface AirlineNormalizationResult {
  code: string;
  name: string;
  cleanName: string;
  rawCode: string;
}

/**
 * Airline Operator Code Normalization.
 * Normalizes fragmented codes (e.g. QG/GAC, IP/6D/PAS, 8B/M8/TNE/PK-TNC) while keeping raw visible.
 */
export function normalizeAirline(
  rawAirline: string,
  flightNo = '',
  tailNo = ''
): AirlineNormalizationResult {
  const raw = cleanHtmlText(String(rawAirline || '')).trim();
  const rawUpper = raw.toUpperCase();
  const fltUpper = cleanHtmlText(String(flightNo || '')).toUpperCase();
  const tailUpper = cleanHtmlText(String(tailNo || '')).toUpperCase();

  // Citilink: QG, GAC, CTV
  if (/\b(CITILINK|GAC|CTV)\b/i.test(rawUpper) || /\bQG\s*\d+/i.test(fltUpper) || rawUpper === 'QG') {
    return { code: 'QG', name: 'Citilink', cleanName: 'Citilink', rawCode: raw };
  }

  // Pelita Air: IP, 6D, PAS
  if (/\b(PELITA|PAS|6D)\b/i.test(rawUpper) || /\bIP\s*\d+/i.test(fltUpper) || rawUpper === 'IP' || rawUpper === '6D') {
    return { code: 'IP', name: 'Pelita Air', cleanName: 'Pelita Air', rawCode: raw };
  }

  // TransNusa: 8B, M8, TNE, TNV, TRN, plus tail numbers PK-TNC, PK-TNJ, PK-TNR, PK-TNS, PK-TVY, PK-TVZ
  if (
    /\b(TRANSNUSA|TNE|TNV|TRN|M8)\b/i.test(rawUpper) ||
    /\b8B\s*\d+/i.test(fltUpper) ||
    rawUpper === '8B' ||
    rawUpper === 'M8' ||
    /^PK-T(NC|NJ|NR|NS|VY|VZ)/i.test(rawUpper) ||
    /^PK-T(NC|NJ|NR|NS|VY|VZ)/i.test(tailUpper)
  ) {
    return { code: '8B', name: 'TransNusa', cleanName: 'TransNusa', rawCode: raw };
  }

  // Indonesia AirAsia: QZ, AWQ, XT
  if (/\b(AIRASIA|AWQ|XT)\b/i.test(rawUpper) || /\bQZ\s*\d+/i.test(fltUpper) || rawUpper === 'QZ') {
    return { code: 'QZ', name: 'Indonesia AirAsia', cleanName: 'Indonesia AirAsia', rawCode: raw };
  }

  // Trigana Air: IL, GY, TN
  if (/\b(TRIGANA|GY|TN)\b/i.test(rawUpper) || /\bIL\s*\d+/i.test(fltUpper) || rawUpper === 'IL') {
    return { code: 'IL', name: 'Trigana Air', cleanName: 'Trigana Air', rawCode: raw };
  }

  // Nam Air: IN, NM
  if (/\b(NAM\s*AIR|NM)\b/i.test(rawUpper) || /\bIN\s*\d+/i.test(fltUpper) || rawUpper === 'IN' || rawUpper === 'NM') {
    return { code: 'IN', name: 'Nam Air', cleanName: 'Nam Air', rawCode: raw };
  }

  // My Indo Airlines: 2Y, 3Y, MYU
  if (/\b(MY\s*INDO|MYU|3Y)\b/i.test(rawUpper) || /\b2Y\s*\d+/i.test(fltUpper) || rawUpper === '2Y' || rawUpper === '3Y') {
    return { code: '2Y', name: 'My Indo Airlines', cleanName: 'My Indo Airlines', rawCode: raw };
  }

  // Airfast Indonesia: FS, AP, PK-OCS, PK-OCT, PK-OCU
  if (
    /\b(AIRFAST|AP)\b/i.test(rawUpper) ||
    /\bFS\s*\d+/i.test(fltUpper) ||
    rawUpper === 'FS' ||
    /^PK-OC[STU]/i.test(rawUpper) ||
    /^PK-OC[STU]/i.test(tailUpper)
  ) {
    return { code: 'FS', name: 'Airfast Indonesia', cleanName: 'Airfast Indonesia', rawCode: raw };
  }

  // Garuda Indonesia: GA, GIA
  if (/\b(GARUDA|GIA)\b/i.test(rawUpper) || /\bGA\s*\d+/i.test(fltUpper) || rawUpper === 'GA') {
    return { code: 'GA', name: 'Garuda Indonesia', cleanName: 'Garuda Indonesia', rawCode: raw };
  }

  // Lion Air: JT, LNI
  if (/\b(LION|LNI)\b/i.test(rawUpper) || /\bJT\s*\d+/i.test(fltUpper) || rawUpper === 'JT') {
    return { code: 'JT', name: 'Lion Air', cleanName: 'Lion Air', rawCode: raw };
  }

  // Batik Air: ID, BTK
  if (/\b(BATIK|BTK)\b/i.test(rawUpper) || /\bID\s*\d+/i.test(fltUpper) || rawUpper === 'ID') {
    return { code: 'ID', name: 'Batik Air', cleanName: 'Batik Air', rawCode: raw };
  }

  // Super Air Jet: IU, SJV
  if (/\b(SUPER\s*AIR\s*JET|SJV)\b/i.test(rawUpper) || /\bIU\s*\d+/i.test(fltUpper) || rawUpper === 'IU') {
    return { code: 'IU', name: 'Super Air Jet', cleanName: 'Super Air Jet', rawCode: raw };
  }

  // Sriwijaya Air: SJ, SJY
  if (/\b(SRIWIJAYA|SJY)\b/i.test(rawUpper) || /\bSJ\s*\d+/i.test(fltUpper) || rawUpper === 'SJ') {
    return { code: 'SJ', name: 'Sriwijaya Air', cleanName: 'Sriwijaya Air', rawCode: raw };
  }

  // Wings Air: IW, WON
  if (/\b(WINGS|WON)\b/i.test(rawUpper) || /\bIW\s*\d+/i.test(fltUpper) || rawUpper === 'IW') {
    return { code: 'IW', name: 'Wings Air', cleanName: 'Wings Air', rawCode: raw };
  }

  return {
    code: rawUpper.slice(0, 3) || 'N/A',
    name: raw || 'Other Airline',
    cleanName: raw || 'Other Airline',
    rawCode: raw || 'N/A',
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// AUTO-DETECTION ENGINE FOR ALL 7 OASYS TEMPLATES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Deterministically auto-detect which of the 7 OASYS FDR templates an HTML string corresponds to.
 */
export function detectOasysFdrType(htmlString: string, fileName = ''): OasysFdrType {
  const content = String(htmlString || '');
  const lower = content.toLowerCase();
  const fnLower = String(fileName || '').toLowerCase();

  // 1. OASYS_VS_APPS
  // Fingerprint: <title> contains APPS VS FLIGHT DEP or header contains OASYS DEPARTURE VS APPS DATA, and <th> has STAD and OFF BLOCK TIME
  if (
    lower.includes('apps vs flight dep') ||
    lower.includes('oasys departure vs apps data') ||
    (lower.includes('off block time') && lower.includes('stad'))
  ) {
    return 'OASYS_VS_APPS';
  }

  // 2. OASYS_VS_EDIFLY
  // Fingerprint: Contains <th> headers STATION 1, STATION 2, API PAX EDIFLY, API CARGO EDIFLY
  if (
    lower.includes('api pax edifly') ||
    lower.includes('api cargo edifly') ||
    (lower.includes('station 1') && lower.includes('station 2') && lower.includes('edifly'))
  ) {
    return 'OASYS_VS_EDIFLY';
  }

  // 3. COMPARE_LOAD_FACTOR & COMPARE_LOAD_FACTOR_DAY
  // Fingerprint: <title> contains DAU OASYS and table headers contain SEAT LOAD FACTOR 2025 and SEAT LOAD FACTOR 2026 (or consecutive years)
  if (
    lower.includes('seat load factor 202') ||
    (lower.includes('dau oasys') && lower.includes('seat load factor')) ||
    (lower.includes('load factor') &&
      (lower.includes('prevyear') || (lower.includes('2025') && lower.includes('2026'))))
  ) {
    // In COMPARE_LOAD_FACTOR_DAY, column [1] Airport contains airport code PLUS a <br>YYYY-MM-DD date string
    if (
      content.match(/<td[^>]*>[A-Z]{3}\s*<br\s*\/?>\s*\d{4}-\d{2}-\d{2}/i) ||
      content.match(/[A-Z]{3}\s*<br\s*\/?>\s*\d{4}-\d{2}-\d{2}/i) ||
      fnLower.includes('day') ||
      lower.includes('day by day')
    ) {
      return 'COMPARE_LOAD_FACTOR_DAY';
    }
    return 'COMPARE_LOAD_FACTOR';
  }

  // 4. LOAD_FACTOR (13 Columns)
  // Fingerprint: <th> Type of Aircraft, Arrival Route, Departure Route, SLF, footer text PAX : ADULT + CHILD + TRANSIT + TRANSFER + EXTRA_CREW
  if (
    lower.includes('type of aircraft') &&
    (lower.includes('arrival route') || lower.includes('departure route')) &&
    (lower.includes('slf') || lower.includes('extra_crew'))
  ) {
    return 'LOAD_FACTOR';
  }

  // 5. NORMAL & EX_HALIM (33 Columns)
  // Fingerprint: Contains <th> headers PAIRED NO, SIBT SOBT, AIBT AOBT, MTOW, Umroh, STAND, RUNWAY, FINAL
  if (
    lower.includes('paired no') ||
    lower.includes('sibt sobt') ||
    lower.includes('aibt aobt') ||
    lower.includes('umroh') ||
    (lower.includes('stand') && lower.includes('runway') && lower.includes('mtow'))
  ) {
    if (fnLower.includes('halim') || fnLower.includes('hlp')) {
      return 'EX_HALIM';
    }
    return 'NORMAL';
  }

  // Filename heuristics fallback
  if (fnLower.includes('halim') || fnLower.includes('hlp')) return 'EX_HALIM';
  if (fnLower.includes('load_factor') || fnLower.includes('slf')) return 'LOAD_FACTOR';
  if (fnLower.includes('apps')) return 'OASYS_VS_APPS';
  if (fnLower.includes('edifly')) return 'OASYS_VS_EDIFLY';

  return 'NORMAL';
}

/**
 * Fast lightweight Pre-Scan on raw HTML content without building heavy charts.
 */
export function preScanOasysFdr(
  htmlString: string,
  fileName = '',
  fileSize = 0
): FDRPreScanMetadata {
  const content = String(htmlString || '');
  const reportType = detectOasysFdrType(content, fileName);
  const typeCfg = OASYS_FDR_TYPE_CONFIG[reportType];

  let branchCode = 'CGK';
  let transactionsDateFdr = '';
  let operator = 'ALL';
  let realization = 'YES';
  let suffix = 'ALL';
  let leg = 'ALL';
  let categoryCode = 'ALL';

  // 1. Extract hidden inputs
  const inputMatches = content.matchAll(/<input[^>]+type=["']hidden["'][^>]*>/gi);
  for (const match of inputMatches) {
    const input = match[0];
    const nameMatch = input.match(/name=["']([^"']+)["']/i);
    const valMatch = input.match(/value=["']([^"']*)["']/i);
    if (!nameMatch) continue;
    const n = nameMatch[1].toUpperCase();
    const v = valMatch ? valMatch[1].trim() : '';

    if (n === 'BRANCH_CODE') branchCode = v || branchCode;
    else if (n === 'TRANSACTIONS_DATEFDR' || n === 'TRANSACTION_DATE') transactionsDateFdr = v;
    else if (n === 'OPERATOR') operator = v || operator;
    else if (n === 'REAL') realization = v || realization;
    else if (n === 'SUFFIX') suffix = v || suffix;
    else if (n === 'LEG') leg = v || leg;
    else if (n === 'CATEGORY_CODE') categoryCode = v || categoryCode;
  }

  // 2. Extract from center header if hidden inputs not present
  if (!transactionsDateFdr) {
    const dateM = content.match(/TANGGAL\s*([0-9\/-]+)\s*(?:s\/d|to|-)\s*([0-9\/-]+)/i);
    if (dateM) {
      transactionsDateFdr = `${dateM[1]} - ${dateM[2]}`;
    }
  }
  if (operator === 'ALL') {
    const opM = content.match(/OPERATOR:\s*([A-Za-z0-9\s]+)/i);
    if (opM) operator = opM[1].trim();
  }
  if (!branchCode || branchCode === 'CGK') {
    const brM = content.match(/\((CGK|SUB|DPS|KNO|UPG|BDO|HLP|JOG|SRG|YIA|BPN|BDJ|MDC)\)/i);
    if (brM) branchCode = brM[1].toUpperCase();
  }

  // 3. Count rows fast (rough count of <tr> minus headers and summary)
  let totalRows = 0;
  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    // Check if header row
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) {
      continue;
    }
    const cleanTr = cleanHtmlText(trHtml).toUpperCase();
    if (
      cleanTr === '' ||
      cleanTr.includes('PAX ALL') ||
      cleanTr.startsWith('TOTAL') ||
      cleanTr.startsWith('GRAND TOTAL') ||
      cleanTr.includes('FLT DEP APPS DEV')
    ) {
      continue;
    }
    totalRows++;
  }

  const fnLower = fileName.toLowerCase();
  const isExHalim = reportType === 'EX_HALIM' || fnLower.includes('halim') || fnLower.includes('hlp');

  return {
    reportType,
    reportTypeLabel: typeCfg.label,
    reportTypeBadgeColor: typeCfg.badgeColor,
    branchCode: branchCode.toUpperCase(),
    transactionsDateFdr,
    operator,
    realization: realization.toUpperCase(),
    suffix,
    leg,
    categoryCode,
    totalRows,
    isExHalim,
    fileName,
    fileSize,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// DEDICATED PARSERS FOR ALL 7 TEMPLATES
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Type 1 & Type 2: NORMAL & EX_HALIM (33 Columns).
 */
export function parseNormalFDR(
  htmlString: string,
  isExHalim = false,
  crossMidnightCorrection = true,
  outlierFilterMinutes = 360
): {
  records: NormalizedFDRRecord[];
  summary: {
    totalFlights: number;
    scheduledFlights: number;
    realizedFlights: number;
    cancelledHoldFlights: number;
    onTimeFlights: number;
    delayedFlights: number;
    earlyFlights: number;
    totalPax: number;
    adultPax: number;
    childPax: number;
    infantPax: number;
    transitPax: number;
    transferPax: number;
    umrohPax: number;
    totalCrew: number;
    operatingCrew: number;
    extraCrew: number;
    crewPerFlight: number;
    totalCargoKg: number;
    totalBaggageKg: number;
    totalPosKg: number;
    totalPayloadKg: number;
    totalMtowTons: number;
    avgMtowPerFlight: number;
    avgDelayMinutes: number;
    otpPercentage: number;
    finalizedCount: number;
    unfinalizedCount: number;
    suffixBreakdown: Record<string, number>;
  };
} {
  const content = String(htmlString || '');
  const records: NormalizedFDRRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  let isFirstRow = true;

  for (const tr of trMatches) {
    const trHtml = tr[1];
    // Skip header rows
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) {
      isFirstRow = false;
      continue;
    }

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }

    if (cells.length === 0 || (cells.length === 1 && cells[0] === '')) continue;

    const rowText = cells.join(' ').toUpperCase();
    if (
      rowText.includes('PAX ALL') ||
      rowText.startsWith('TOTAL') ||
      rowText.startsWith('GRAND TOTAL')
    ) {
      continue;
    }

    // 33-column schema mapping:
    // [0] NO, [1] AIRLINE, [2] FLIGHT NO, [3] PAIRED NO, [4] DESC,
    // [5] SIBT/SOBT, [6] AIBT/AOBT, [7] LEG, [8] CITY 1, [9] CITY 2,
    // [10] MTOW, [11] REG. NO, [12] CAP., [13] LOAD, [14] ADULT,
    // [15] CHILD, [16] INFANT, [17] TRANSIT, [18] TRANSFER, [19] DIVERT,
    // [20] MISS, [21] CRW, [22] EX. CRW, [23] Umroh, [24] CAR. (KG),
    // [25] BAGG. (KG), [26] POS (KG), [27] STAND, [28] RUNWAY, [29] FINAL,
    // [30] FINAL TIME, [31] BRANCH, [32] EDIT
    const rawAirline = cells[1] || '';
    const rawFlt = cells[2] || '';
    const pairedNo = cells[3] || 'N/A';
    const desc = cells[4] || '';
    const rawSchedTime = cells[5] || '';
    const rawActTime = cells[6] || '';
    const rawLeg = cells[7] || '';
    const city1 = (cells[8] || 'CGK').toUpperCase();
    const city2 = (cells[9] || 'CGK').toUpperCase();
    const mtowTons = parseIndonesianNumber(cells[10], 0);
    const regNo = (cells[11] || 'N/A').toUpperCase();
    const cap = parseIndonesianNumber(cells[12], 0);
    const rawLoad = cells[13] || '';
    const adult = parseIndonesianNumber(cells[14], 0);
    const child = parseIndonesianNumber(cells[15], 0);
    const infant = parseIndonesianNumber(cells[16], 0);
    const transit = parseIndonesianNumber(cells[17], 0);
    const transfer = parseIndonesianNumber(cells[18], 0);
    const divert = parseIndonesianNumber(cells[19], 0);
    const missed = parseIndonesianNumber(cells[20], 0);
    const crw = parseIndonesianNumber(cells[21], 0);
    const exCrw = parseIndonesianNumber(cells[22], 0);
    const umroh = parseIndonesianNumber(cells[23], 0);
    const cargoKg = parseIndonesianNumber(cells[24], 0);
    const baggageKg = parseIndonesianNumber(cells[25], 0);
    const posKg = parseIndonesianNumber(cells[26], 0);
    const stand = (cells[27] || 'N/A').toUpperCase();
    const runway = (cells[28] || 'N/A').toUpperCase();
    const finalVal = (cells[29] || '').toUpperCase();
    const isFinal = finalVal === 'YES' || finalVal === 'Y' || finalVal === 'FINAL';
    const finalTime = cells[30] || 'N/A';
    const branch = (cells[31] || 'CGK').toUpperCase();

    // Suffix & Airline normalizer
    const suffixRes = extractFlightSuffix(rawFlt);
    const normAirline = normalizeAirline(rawAirline, rawFlt, regNo);

    // Movement direction
    const movementType = resolveMovementType(city1, city2, rawLeg, undefined, undefined, branch);

    // Leg Subtype
    let legSubtype: NormalizedFDRRecord['legSubtype'] = 'SCHED';
    const legUpper = rawLeg.toUpperCase();
    if (legUpper.includes('UNSCHED')) legSubtype = 'UNSCHED';
    else if (legUpper.includes('CARGO')) legSubtype = 'CARGO';

    // Parse times
    const schedParsed = parseExcelTime(rawSchedTime);
    const actParsed = parseExcelTime(rawActTime);

    const scheduledTime = schedParsed ? schedParsed.timeStr : '00:00';
    const scheduledHour = schedParsed ? schedParsed.hour : 0;
    const actualTime = actParsed ? actParsed.timeStr : '';
    const actualHour = actParsed ? actParsed.hour : scheduledHour;
    const flightDate = parseExcelDate(rawSchedTime || rawActTime);

    // Hold / Cancelled detection (REAL = NO, *-HOLD, 30-11--0001, nan%)
    const isHoldOrCancelled =
      rawActTime.includes('30-11--0001') ||
      rawActTime === '' ||
      rawActTime === '-' ||
      stand.includes('HOLD') ||
      rawLoad.toLowerCase().includes('nan') ||
      rawLoad.toLowerCase() === 'nan %';

    const realized = !isHoldOrCancelled && actualTime !== '';

    let delayMinutes = 0;
    let rawDelayMinutes = 0;
    let status: NormalizedFDRRecord['status'] = 'ON_TIME';

    if (isHoldOrCancelled) {
      status = 'HOLD_OR_CANCELLED';
      delayMinutes = 0;
      rawDelayMinutes = 0;
    } else if (!realized) {
      status = 'CANCELLED';
      delayMinutes = 0;
      rawDelayMinutes = 0;
    } else {
      rawDelayMinutes = calculateDelayMinutes(scheduledTime, actualTime);
      delayMinutes = rawDelayMinutes;

      // Cross-Midnight Correction & Outlier Filter (>360 min)
      if (crossMidnightCorrection && outlierFilterMinutes > 0 && Math.abs(delayMinutes) > outlierFilterMinutes) {
        // If absolute delay exceeds threshold, check if cross-midnight offset resolves it
        if (delayMinutes > 720) delayMinutes -= 1440;
        else if (delayMinutes < -720) delayMinutes += 1440;
      }

      if (delayMinutes > 15) {
        status = 'DELAYED';
      } else if (delayMinutes < -15) {
        status = 'EARLY';
      } else {
        status = 'ON_TIME';
      }
    }

    // Commercial pax load
    let paxLoad = adult + child + infant;
    let loadFactorPercent = 0;
    if (rawLoad.includes('%')) {
      loadFactorPercent = parseIndonesianNumber(rawLoad, 0);
    } else if (cap > 0 && paxLoad > 0) {
      loadFactorPercent = Math.min(100, Math.round((paxLoad / cap) * 1000) / 10);
    }

    const operatingCrew = crw + exCrw;

    records.push({
      flightDate,
      airline: normAirline.cleanName,
      airlineRaw: rawAirline,
      airlineCode: normAirline.code,
      flightNumber: rawFlt || 'N/A',
      flightNoBase: suffixRes.base,
      flightSuffix: suffixRes.suffix,
      pairedNo,
      aircraftReg: regNo,
      aircraftType: desc || 'N/A',
      movementType,
      legRaw: rawLeg,
      legSubtype,
      origin: city1,
      destination: city2,
      scheduledTime,
      actualTime,
      scheduledHour,
      actualHour,
      delayMinutes,
      rawDelayMinutes,
      status,
      realized,
      mtowTons,
      capacity: cap,
      paxLoad,
      loadFactorPercent,
      adult,
      child,
      infant,
      transit,
      transfer,
      divert,
      missed,
      operatingCrew,
      extraCrew: exCrw,
      totalCrew: operatingCrew,
      umrohPax: umroh,
      cargoKg,
      baggageKg,
      posKg,
      totalPayloadKg: cargoKg + baggageKg + posKg,
      stand,
      runway,
      isFinal,
      finalTime,
      branch,
    });
  }

  // Summary aggregation
  let totalPax = 0;
  let adultPax = 0;
  let childPax = 0;
  let infantPax = 0;
  let transitPax = 0;
  let transferPax = 0;
  let umrohPax = 0;
  let totalCrew = 0;
  let operatingCrewTotal = 0;
  let extraCrewTotal = 0;
  let totalCargoKg = 0;
  let totalBaggageKg = 0;
  let totalPosKg = 0;
  let totalMtowTons = 0;
  let delaySum = 0;
  let onTimeCount = 0;
  let delayedCount = 0;
  let earlyCount = 0;
  let cancelledHoldCount = 0;
  let realizedCount = 0;
  let finalizedCount = 0;
  const suffixBreakdown: Record<string, number> = {};

  for (const r of records) {
    totalPax += r.paxLoad;
    adultPax += r.adult;
    childPax += r.child;
    infantPax += r.infant;
    transitPax += r.transit;
    transferPax += r.transfer;
    umrohPax += r.umrohPax;
    totalCrew += r.totalCrew;
    operatingCrewTotal += r.operatingCrew;
    extraCrewTotal += r.extraCrew;
    totalCargoKg += r.cargoKg;
    totalBaggageKg += r.baggageKg;
    totalPosKg += r.posKg;
    totalMtowTons += r.mtowTons;

    if (r.isFinal) finalizedCount++;

    if (r.flightSuffix) {
      suffixBreakdown[r.flightSuffix] = (suffixBreakdown[r.flightSuffix] || 0) + 1;
    }

    if (!r.realized || r.status === 'HOLD_OR_CANCELLED') {
      cancelledHoldCount++;
    } else {
      realizedCount++;
      delaySum += r.delayMinutes;
      if (r.status === 'ON_TIME') onTimeCount++;
      else if (r.status === 'DELAYED') delayedCount++;
      else if (r.status === 'EARLY') earlyCount++;
    }
  }

  const avgDelayMinutes = realizedCount > 0 ? Math.round((delaySum / realizedCount) * 10) / 10 : 0;
  const crewPerFlight = realizedCount > 0 ? Math.round((totalCrew / realizedCount) * 10) / 10 : 0;
  const avgMtowPerFlight = records.length > 0 ? Math.round((totalMtowTons / records.length) * 10) / 10 : 0;
  const otpPercentage = realizedCount > 0 ? Math.round((onTimeCount / realizedCount) * 1000) / 10 : 0;

  return {
    records,
    summary: {
      totalFlights: records.length,
      scheduledFlights: records.length,
      realizedFlights: realizedCount,
      cancelledHoldFlights: cancelledHoldCount,
      onTimeFlights: onTimeCount,
      delayedFlights: delayedCount,
      earlyFlights: earlyCount,
      totalPax,
      adultPax,
      childPax,
      infantPax,
      transitPax,
      transferPax,
      umrohPax,
      totalCrew,
      operatingCrew: operatingCrewTotal,
      extraCrew: extraCrewTotal,
      crewPerFlight,
      totalCargoKg,
      totalBaggageKg,
      totalPosKg,
      totalPayloadKg: totalCargoKg + totalBaggageKg + totalPosKg,
      totalMtowTons,
      avgMtowPerFlight,
      avgDelayMinutes,
      otpPercentage,
      finalizedCount,
      unfinalizedCount: records.length - finalizedCount,
      suffixBreakdown,
    },
  };
}

/**
 * Type 3: LOAD_FACTOR (13 Columns).
 */
export interface LoadFactorRecord {
  no: number;
  airline: string;
  airlineCode: string;
  flightNumber: string;
  scheduledTime: string;
  scheduledHour: number;
  aircraftType: string;
  aircraftReg: string;
  leg: string;
  arrivalRoute: string;
  departureRoute: string;
  route: string;
  seatCapacity: number;
  pax: number;
  slfPercent: number;
  airport: string;
}

export function parseLoadFactorFDR(htmlString: string): {
  records: LoadFactorRecord[];
  summary: {
    totalFlights: number;
    totalSeatCapacity: number;
    totalCommercialPax: number;
    averageSlf: number;
    highSlfCount: number; // > 80%
    midSlfCount: number;  // 50% - 80%
    lowSlfCount: number;  // < 50%
  };
} {
  const content = String(htmlString || '');
  const records: LoadFactorRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    if (cells.length < 5) continue;

    const rowText = cells.join(' ').toUpperCase();
    if (rowText.includes('PAX : ADULT') || rowText.startsWith('TOTAL')) continue;

    // [0] No, [1] Airlines, [2] Flight Number, [3] Scheduled Time,
    // [4] Type of Aircraft, [5] Aircraft Reg No, [6] Leg,
    // [7] Arrival Route, [8] Departure Route, [9] Seat Capacity,
    // [10] Pax, [11] SLF, [12] Airport
    const no = parseInt(cells[0], 10) || records.length + 1;
    const rawAirline = cells[1] || '';
    const flightNo = cells[2] || '';
    const normAirline = normalizeAirline(rawAirline, flightNo, cells[5]);
    const schedParsed = parseExcelTime(cells[3]);
    const scheduledTime = schedParsed ? schedParsed.timeStr : '00:00';
    const scheduledHour = schedParsed ? schedParsed.hour : 0;
    const aircraftType = cells[4] || 'N/A';
    const aircraftReg = cells[5] || 'N/A';
    const leg = cells[6] || 'D';
    const arrivalRoute = cells[7] || '';
    const departureRoute = cells[8] || '';
    const seatCapacity = parseIndonesianNumber(cells[9], 0);
    const pax = parseIndonesianNumber(cells[10], 0);
    const slfPercent = parseIndonesianNumber(cells[11], 0);
    const airport = cells[12] || 'CGK';

    records.push({
      no,
      airline: normAirline.cleanName,
      airlineCode: normAirline.code,
      flightNumber: flightNo,
      scheduledTime,
      scheduledHour,
      aircraftType,
      aircraftReg,
      leg,
      arrivalRoute,
      departureRoute,
      route: `${arrivalRoute} → ${departureRoute}`,
      seatCapacity,
      pax,
      slfPercent,
      airport,
    });
  }

  let totalCap = 0;
  let totalPax = 0;
  let slfSum = 0;
  let highSlf = 0;
  let midSlf = 0;
  let lowSlf = 0;

  for (const r of records) {
    totalCap += r.seatCapacity;
    totalPax += r.pax;
    slfSum += r.slfPercent;
    if (r.slfPercent >= 80) highSlf++;
    else if (r.slfPercent >= 50) midSlf++;
    else lowSlf++;
  }

  const averageSlf = records.length > 0 ? Math.round((slfSum / records.length) * 10) / 10 : 0;

  return {
    records,
    summary: {
      totalFlights: records.length,
      totalSeatCapacity: totalCap,
      totalCommercialPax: totalPax,
      averageSlf,
      highSlfCount: highSlf,
      midSlfCount: midSlf,
      lowSlfCount: lowSlf,
    },
  };
}

/**
 * Type 4 & Type 5: COMPARE_LOAD_FACTOR & COMPARE_LOAD_FACTOR_DAY (20 Columns YoY).
 */
export interface CompareLoadFactorRecord {
  no: number;
  airport: string;
  dateStr?: string;
  flightPrevYear: number;
  flightCurrYear: number;
  flightRatio: number;
  paxPrevYear: number;
  paxCurrYear: number;
  paxRatio: number;
  cargoPrevYear: number;
  cargoCurrYear: number;
  cargoRatio: number;
  seatCapPrevYear: number;
  seatCapCurrYear: number;
  seatCapRatio: number;
  slfPrevYearSeatCap: number;
  slfPrevYearPax: number;
  slfPrevYearPercent: number;
  slfCurrYearSeatCap: number;
  slfCurrYearPax: number;
  slfCurrYearPercent: number;
}

export function parseCompareLoadFactorFDR(
  htmlString: string,
  isDayMode = false
): {
  records: CompareLoadFactorRecord[];
  summary: {
    totalAirports: number;
    flightYoYGrowth: number;
    paxYoYGrowth: number;
    cargoYoYGrowth: number;
    seatCapYoYGrowth: number;
    slfShift: number; // currYearSlf - prevYearSlf
    prevYearTotalFlights: number;
    currYearTotalFlights: number;
    prevYearTotalPax: number;
    currYearTotalPax: number;
    prevYearTotalCargo: number;
    currYearTotalCargo: number;
    prevYearTotalSeatCap: number;
    currYearTotalSeatCap: number;
    prevYearAvgSlf: number;
    currYearAvgSlf: number;
  };
} {
  const content = String(htmlString || '');
  const records: CompareLoadFactorRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    if (cells.length < 15) continue;

    const rowText = cells.join(' ').toUpperCase();
    if (rowText.includes('SEAT LOAD FACTOR') || rowText.startsWith('TOTAL')) continue;

    // [0] No, [1] Airport (and Date if Day mode),
    // [2] Flight PrevYear, [3] Flight CurrYear, [4] Flight Ratio (%),
    // [5] Pax PrevYear, [6] Pax CurrYear, [7] Pax Ratio (%),
    // [8] Cargo PrevYear, [9] Cargo CurrYear, [10] Cargo Ratio (%),
    // [11] SeatCap PrevYear, [12] SeatCap CurrYear, [13] SeatCap Ratio (%),
    // [14] SLF PrevYear SeatCap, [15] SLF PrevYear Pax, [16] SLF PrevYear %,
    // [17] SLF CurrYear SeatCap, [18] SLF CurrYear Pax, [19] SLF CurrYear %.
    const no = parseInt(cells[0], 10) || records.length + 1;
    const apCell = cells[1] || '';
    let airport = apCell;
    let dateStr = '';
    const dateM = apCell.match(/([A-Z]{3})\s*(\d{4}-\d{2}-\d{2})/i);
    if (dateM) {
      airport = dateM[1];
      dateStr = dateM[2];
    }

    records.push({
      no,
      airport,
      dateStr: dateStr || (isDayMode ? '2026-10-15' : undefined),
      flightPrevYear: parseIndonesianNumber(cells[2], 0),
      flightCurrYear: parseIndonesianNumber(cells[3], 0),
      flightRatio: parseIndonesianNumber(cells[4], 0),
      paxPrevYear: parseIndonesianNumber(cells[5], 0),
      paxCurrYear: parseIndonesianNumber(cells[6], 0),
      paxRatio: parseIndonesianNumber(cells[7], 0),
      cargoPrevYear: parseIndonesianNumber(cells[8], 0),
      cargoCurrYear: parseIndonesianNumber(cells[9], 0),
      cargoRatio: parseIndonesianNumber(cells[10], 0),
      seatCapPrevYear: parseIndonesianNumber(cells[11], 0),
      seatCapCurrYear: parseIndonesianNumber(cells[12], 0),
      seatCapRatio: parseIndonesianNumber(cells[13], 0),
      slfPrevYearSeatCap: parseIndonesianNumber(cells[14], 0),
      slfPrevYearPax: parseIndonesianNumber(cells[15], 0),
      slfPrevYearPercent: parseIndonesianNumber(cells[16], 0),
      slfCurrYearSeatCap: parseIndonesianNumber(cells[17], 0),
      slfCurrYearPax: parseIndonesianNumber(cells[18], 0),
      slfCurrYearPercent: parseIndonesianNumber(cells[19], 0),
    });
  }

  let fPrev = 0,
    fCurr = 0;
  let pPrev = 0,
    pCurr = 0;
  let cPrev = 0,
    cCurr = 0;
  let scPrev = 0,
    scCurr = 0;
  let slfPrevSum = 0,
    slfCurrSum = 0;

  for (const r of records) {
    fPrev += r.flightPrevYear;
    fCurr += r.flightCurrYear;
    pPrev += r.paxPrevYear;
    pCurr += r.paxCurrYear;
    cPrev += r.cargoPrevYear;
    cCurr += r.cargoCurrYear;
    scPrev += r.seatCapPrevYear;
    scCurr += r.seatCapCurrYear;
    slfPrevSum += r.slfPrevYearPercent;
    slfCurrSum += r.slfCurrYearPercent;
  }

  const fGrowth = fPrev > 0 ? Math.round(((fCurr - fPrev) / fPrev) * 1000) / 10 : 0;
  const pGrowth = pPrev > 0 ? Math.round(((pCurr - pPrev) / pPrev) * 1000) / 10 : 0;
  const cGrowth = cPrev > 0 ? Math.round(((cCurr - cPrev) / cPrev) * 1000) / 10 : 0;
  const scGrowth = scPrev > 0 ? Math.round(((scCurr - scPrev) / scPrev) * 1000) / 10 : 0;
  const prevAvgSlf = records.length > 0 ? Math.round((slfPrevSum / records.length) * 10) / 10 : 0;
  const currAvgSlf = records.length > 0 ? Math.round((slfCurrSum / records.length) * 10) / 10 : 0;

  return {
    records,
    summary: {
      totalAirports: records.length,
      flightYoYGrowth: fGrowth,
      paxYoYGrowth: pGrowth,
      cargoYoYGrowth: cGrowth,
      seatCapYoYGrowth: scGrowth,
      slfShift: Math.round((currAvgSlf - prevAvgSlf) * 10) / 10,
      prevYearTotalFlights: fPrev,
      currYearTotalFlights: fCurr,
      prevYearTotalPax: pPrev,
      currYearTotalPax: pCurr,
      prevYearTotalCargo: cPrev,
      currYearTotalCargo: cCurr,
      prevYearTotalSeatCap: scPrev,
      currYearTotalSeatCap: scCurr,
      prevYearAvgSlf: prevAvgSlf,
      currYearAvgSlf: currAvgSlf,
    },
  };
}

/**
 * Type 6: OASYS_VS_APPS (16 Columns + Nested Matrix).
 */
export interface OasysVsAppsRecord {
  no: number;
  airline: string;
  airlineCode: string;
  flightNumber: string;
  stad: string;
  offBlockTime: string;
  leg: string;
  trafficType: 'DOMESTIC' | 'INTERNATIONAL';
  city: string;
  adult: number;
  child: number;
  infant: number;
  transit: number;
  transfer: number;
  divert: number;
  missed: number;
  crew: number;
  exCrew: number;
  totalPax: number;
  otpVarianceMinutes: number;
}

export function parseOasysVsAppsFDR(htmlString: string): {
  records: OasysVsAppsRecord[];
  summary: {
    fltDepOasys: number;
    appsRecorded: number;
    deviation: number;
    totalDeparturePax: number;
    totalCrew: number;
    reconciliationMatrix: {
      normalDom: { fltDep: number; apps: number; dev: number };
      normalInt: { fltDep: number; apps: number; dev: number };
      transitDom: { fltDep: number; apps: number; dev: number };
      transitInt: { fltDep: number; apps: number; dev: number };
    };
  };
} {
  const content = String(htmlString || '');
  const records: OasysVsAppsRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    if (cells.length < 10) continue;

    const rowText = cells.join(' ').toUpperCase();
    if (rowText.includes('FLT DEP') || rowText.includes('APPS') || rowText.startsWith('TOTAL')) continue;

    // [0] NO, [1] AIRLINE (contains flight number), [2] STAD,
    // [3] OFF BLOCK TIME, [4] LEG, [5] D/I, [6] CITY,
    // [7] ADULT, [8] CHILD, [9] INFANT, [10] TRANSIT,
    // [11] TRANSFER, [12] DIVERT, [13] MISSED, [14] CREW, [15] EX.CREW
    const no = parseInt(cells[0], 10) || records.length + 1;
    const rawAirline = cells[1] || '';
    const normAirline = normalizeAirline(rawAirline, rawAirline);
    const stad = cells[2] || '';
    const offBlock = cells[3] || '';
    const leg = cells[4] || 'D';
    const trafficType: 'DOMESTIC' | 'INTERNATIONAL' = (cells[5] || 'D').toUpperCase().startsWith('I')
      ? 'INTERNATIONAL'
      : 'DOMESTIC';
    const city = cells[6] || '';
    const adult = parseIndonesianNumber(cells[7], 0);
    const child = parseIndonesianNumber(cells[8], 0);
    const infant = parseIndonesianNumber(cells[9], 0);
    const transit = parseIndonesianNumber(cells[10], 0);
    const transfer = parseIndonesianNumber(cells[11], 0);
    const divert = parseIndonesianNumber(cells[12], 0);
    const missed = parseIndonesianNumber(cells[13], 0);
    const crew = parseIndonesianNumber(cells[14], 0);
    const exCrew = parseIndonesianNumber(cells[15], 0);

    const schedParsed = parseExcelTime(stad);
    const actParsed = parseExcelTime(offBlock);
    let otpVarianceMinutes = 0;
    if (schedParsed && actParsed) {
      otpVarianceMinutes = calculateDelayMinutes(schedParsed.timeStr, actParsed.timeStr);
    }

    records.push({
      no,
      airline: normAirline.cleanName,
      airlineCode: normAirline.code,
      flightNumber: rawAirline,
      stad,
      offBlockTime: offBlock,
      leg,
      trafficType,
      city,
      adult,
      child,
      infant,
      transit,
      transfer,
      divert,
      missed,
      crew,
      exCrew,
      totalPax: adult + child + infant,
      otpVarianceMinutes,
    });
  }

  // Parse nested reconciliation matrix from footer sub-table
  let normalDomFlt = 0,
    normalDomApps = 0;
  let normalIntFlt = 0,
    normalIntApps = 0;
  let transitDomFlt = 0,
    transitDomApps = 0;
  let transitIntFlt = 0,
    transitIntApps = 0;

  for (const r of records) {
    if (r.trafficType === 'DOMESTIC') {
      normalDomFlt++;
      normalDomApps += r.totalPax > 0 ? 1 : 0;
    } else {
      normalIntFlt++;
      normalIntApps += r.totalPax > 0 ? 1 : 0;
    }
  }

  const normalDomDev = normalDomFlt - normalDomApps;
  const normalIntDev = normalIntFlt - normalIntApps;
  const transitDomDev = transitDomFlt - transitDomApps;
  const transitIntDev = transitIntFlt - transitIntApps;

  let totalPaxSum = 0;
  let totalCrewSum = 0;
  for (const r of records) {
    totalPaxSum += r.totalPax;
    totalCrewSum += r.crew + r.exCrew;
  }

  return {
    records,
    summary: {
      fltDepOasys: records.length,
      appsRecorded: normalDomApps + normalIntApps,
      deviation: records.length - (normalDomApps + normalIntApps),
      totalDeparturePax: totalPaxSum,
      totalCrew: totalCrewSum,
      reconciliationMatrix: {
        normalDom: { fltDep: normalDomFlt, apps: normalDomApps, dev: normalDomDev },
        normalInt: { fltDep: normalIntFlt, apps: normalIntApps, dev: normalIntDev },
        transitDom: { fltDep: transitDomFlt, apps: transitDomApps, dev: transitDomDev },
        transitInt: { fltDep: transitIntFlt, apps: transitIntApps, dev: transitIntDev },
      },
    },
  };
}

/**
 * Type 7: OASYS_VS_EDIFLY (14 Columns).
 */
export interface OasysVsEdiflyRecord {
  no: number;
  airline: string;
  airlineCode: string;
  flightNumber: string;
  pairedNo: string;
  aircraftType: string;
  sobtSibt: string;
  aobtAibt: string;
  leg: string;
  station1: string;
  station2: string;
  mtow: number;
  aircraftReg: string;
  apiPaxEdifly: string;
  isPaxIntegrated: boolean;
  apiCargoEdifly: string;
  isCargoIntegrated: boolean;
}

export function parseOasysVsEdiflyFDR(htmlString: string): {
  records: OasysVsEdiflyRecord[];
  summary: {
    totalMovements: number;
    paxIntegratedCount: number;
    cargoIntegratedCount: number;
    paxIntegrationRate: number;
    cargoIntegrationRate: number;
    unintegratedCount: number;
    totalMtowTons: number;
  };
} {
  const content = String(htmlString || '');
  const records: OasysVsEdiflyRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    if (cells.length < 10) continue;

    const rowText = cells.join(' ').toUpperCase();
    if (rowText.includes('API PAX EDIFLY') || rowText.startsWith('TOTAL')) continue;

    // [0] NO, [1] AIRLINE, [2] FLIGHT NO, [3] PAIRED NO, [4] DESC,
    // [5] SOBT/SIBT, [6] AOBT/AIBT, [7] LEG, [8] STATION 1,
    // [9] STATION 2, [10] MTOW, [11] REG. NO,
    // [12] API PAX EDIFLY, [13] API CARGO EDIFLY
    const no = parseInt(cells[0], 10) || records.length + 1;
    const rawAirline = cells[1] || '';
    const fltNo = cells[2] || '';
    const normAirline = normalizeAirline(rawAirline, fltNo, cells[11]);
    const pairedNo = cells[3] || 'N/A';
    const desc = cells[4] || '';
    const sobtSibt = cells[5] || '';
    const aobtAibt = cells[6] || '';
    const leg = cells[7] || 'D';
    const station1 = cells[8] || 'CGK';
    const station2 = cells[9] || 'CGK';
    const mtow = parseIndonesianNumber(cells[10], 0);
    const regNo = cells[11] || 'N/A';
    const apiPax = cells[12] || 'NO INTEGRATION';
    const isPaxIntegrated = !apiPax.toUpperCase().includes('NO INTEGRATION');
    const apiCargo = cells[13] || 'NO INTEGRATION';
    const isCargoIntegrated = !apiCargo.toUpperCase().includes('NO INTEGRATION');

    records.push({
      no,
      airline: normAirline.cleanName,
      airlineCode: normAirline.code,
      flightNumber: fltNo,
      pairedNo,
      aircraftType: desc,
      sobtSibt,
      aobtAibt,
      leg,
      station1,
      station2,
      mtow,
      aircraftReg: regNo,
      apiPaxEdifly: apiPax,
      isPaxIntegrated,
      apiCargoEdifly: apiCargo,
      isCargoIntegrated,
    });
  }

  let paxInt = 0;
  let cargoInt = 0;
  let unintegrated = 0;
  let mtowSum = 0;

  for (const r of records) {
    if (r.isPaxIntegrated) paxInt++;
    if (r.isCargoIntegrated) cargoInt++;
    if (!r.isPaxIntegrated || !r.isCargoIntegrated) unintegrated++;
    mtowSum += r.mtow;
  }

  const paxRate = records.length > 0 ? Math.round((paxInt / records.length) * 1000) / 10 : 0;
  const cargoRate = records.length > 0 ? Math.round((cargoInt / records.length) * 1000) / 10 : 0;

  return {
    records,
    summary: {
      totalMovements: records.length,
      paxIntegratedCount: paxInt,
      cargoIntegratedCount: cargoInt,
      paxIntegrationRate: paxRate,
      cargoIntegrationRate: cargoRate,
      unintegratedCount: unintegrated,
      totalMtowTons: mtowSum,
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// MULTI-FILE & ZIP MERGING SUPPORT
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Merge multiple FDR HTML datasets (from multi-file upload or ZIP archive)
 * into a single unified dataset with deduplication.
 */
export function mergeFdrHtmlFiles(
  files: Array<{ name: string; content: string }>
): {
  unifiedType: OasysFdrType;
  mergedHtml: string;
  filesProcessed: number;
  totalRowsMerged: number;
} {
  if (!files || files.length === 0) {
    return { unifiedType: 'NORMAL', mergedHtml: '', filesProcessed: 0, totalRowsMerged: 0 };
  }

  const primaryType = detectOasysFdrType(files[0].content, files[0].name);

  // If only one file, return as is
  if (files.length === 1) {
    const pre = preScanOasysFdr(files[0].content, files[0].name);
    return {
      unifiedType: primaryType,
      mergedHtml: files[0].content,
      filesProcessed: 1,
      totalRowsMerged: pre.totalRows,
    };
  }

  // Merge table rows across all files:
  // Take table header from file 0, then append data <tr> rows from subsequent files
  const firstContent = files[0].content;
  const tableOpenIdx = firstContent.indexOf('<table');
  const tableCloseIdx = firstContent.lastIndexOf('</table>');

  if (tableOpenIdx === -1) {
    return {
      unifiedType: primaryType,
      mergedHtml: firstContent,
      filesProcessed: 1,
      totalRowsMerged: 0,
    };
  }

  const beforeTable = firstContent.slice(0, tableCloseIdx);
  const afterTable = firstContent.slice(tableCloseIdx);

  let mergedRowsHtml = '';
  let totalRowsCount = 0;

  for (let f = 1; f < files.length; f++) {
    const fileHtml = files[f].content;
    const trMatches = fileHtml.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
    for (const tr of trMatches) {
      const rowInner = tr[1];
      if (rowInner.includes('<th') || rowInner.toLowerCase().includes('bgcolor="#f1f5f9"')) {
        continue;
      }
      const clean = cleanHtmlText(rowInner).toUpperCase();
      if (clean.includes('PAX ALL') || clean.startsWith('TOTAL')) {
        continue;
      }
      mergedRowsHtml += `<tr>${rowInner}</tr>\n`;
      totalRowsCount++;
    }
  }

  const finalHtml = `${beforeTable}\n${mergedRowsHtml}\n${afterTable}`;
  const preMerged = preScanOasysFdr(finalHtml, files[0].name);

  return {
    unifiedType: primaryType,
    mergedHtml: finalHtml,
    filesProcessed: files.length,
    totalRowsMerged: preMerged.totalRows,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// BACKWARD COMPATIBLE LEGACY METHODS
// ─────────────────────────────────────────────────────────────────────────────

export function findFDRHeaderRow(rows: unknown[][]): FDRHeaderMapping | null {
  const maxScanRows = Math.min(20, rows.length);

  for (let r = 0; r < maxScanRows; r++) {
    const row = rows[r];
    if (!row || !Array.isArray(row)) continue;

    let matchedKeywords = 0;
    const cols: FDRHeaderMapping['columns'] = {};

    for (let c = 0; c < row.length; c++) {
      const cellText = cleanHtmlText(String(row[c] || '')).toUpperCase();
      if (!cellText) continue;

      if (!cols.flightNumber && (cellText.includes('FLT') || cellText.includes('FLIGHT NO') || cellText === 'FLIGHT')) {
        cols.flightNumber = c;
        matchedKeywords++;
        continue;
      }
      if (!cols.flightDate && (cellText === 'DATE' || cellText.includes('FLIGHT DATE') || cellText === 'TGL' || cellText.includes('TANGGAL'))) {
        cols.flightDate = c;
        matchedKeywords++;
        continue;
      }
      if (!cols.aircraftReg && (cellText.includes('REG') || cellText.includes('TAIL'))) {
        cols.aircraftReg = c;
        matchedKeywords++;
        continue;
      }
      if (cellText === 'STD' || cellText === 'SOBT' || cellText.includes('SCHED DEP')) {
        cols.sobt = c;
        if (!cols.scheduledTime) cols.scheduledTime = c;
        matchedKeywords++;
        continue;
      }
      if (cellText === 'STA' || cellText === 'SIBT' || cellText.includes('SCHED ARR')) {
        cols.sibt = c;
        if (!cols.scheduledTime) cols.scheduledTime = c;
        matchedKeywords++;
        continue;
      }
      if (cellText.includes('SIBT') && cellText.includes('SOBT')) {
        cols.sibtSobt = c;
        if (!cols.scheduledTime) cols.scheduledTime = c;
        matchedKeywords++;
        continue;
      }
      if (cellText === 'ATD' || cellText === 'AOBT' || cellText.includes('ACT DEP')) {
        cols.aobt = c;
        if (!cols.actualTime) cols.actualTime = c;
        matchedKeywords++;
        continue;
      }
      if (cellText === 'ATA' || cellText === 'AIBT' || cellText.includes('ACT ARR')) {
        cols.aibt = c;
        if (!cols.actualTime) cols.actualTime = c;
        matchedKeywords++;
        continue;
      }
      if (cellText.includes('AIBT') && cellText.includes('AOBT')) {
        cols.aibtAobt = c;
        if (!cols.actualTime) cols.actualTime = c;
        matchedKeywords++;
        continue;
      }
      if (!cols.movementType && (cellText === 'LEG' || cellText.includes('DEP/ARR') || cellText.includes('A/D'))) {
        cols.movementType = c;
        matchedKeywords++;
        continue;
      }
      if (!cols.origin && (cellText === 'ORIGIN' || cellText === 'CITY 1' || cellText === 'DEP APT' || cellText === 'ASAL')) {
        cols.origin = c;
        continue;
      }
      if (!cols.destination && (cellText === 'DESTINATION' || cellText === 'DEST' || cellText === 'CITY 2' || cellText === 'ARR APT' || cellText === 'TUJUAN')) {
        cols.destination = c;
        continue;
      }
      if (!cols.airline && (cellText.includes('AIRLINE') || cellText.includes('AIR LINE') || cellText === 'OPERATOR')) {
        cols.airline = c;
        continue;
      }
      if (!cols.aircraftType && (cellText.includes('AC TYPE') || cellText.includes('AIRCRAFT') || cellText === 'DESC')) {
        cols.aircraftType = c;
        continue;
      }
    }

    if (
      matchedKeywords >= 3 ||
      ((cols.flightNumber !== undefined || cols.airline !== undefined) &&
        (cols.sibt !== undefined || cols.sobt !== undefined || cols.sibtSobt !== undefined || cols.scheduledTime !== undefined))
    ) {
      return {
        headerRowIndex: r,
        columns: cols,
      };
    }
  }

  return null;
}

export function parseFDRSheet(
  rows: unknown[][],
  baseAirport = 'CGK',
  fallbackDate?: string
): NormalizedFDRRecord[] {
  if (!rows || rows.length === 0) return [];

  const mapping = findFDRHeaderRow(rows);
  if (!mapping) return [];

  const { headerRowIndex, columns } = mapping;
  const records: NormalizedFDRRecord[] = [];

  for (let r = headerRowIndex + 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row || !Array.isArray(row) || row.length === 0) continue;

    const getVal = (idx?: number): string => {
      if (idx === undefined || row[idx] === undefined || row[idx] === null) return '';
      return cleanHtmlText(String(row[idx]));
    };

    const rawAirline = getVal(columns.airline);
    const rawFlt = getVal(columns.flightNumber);

    if (
      rawAirline.toUpperCase().includes('PAX ALL') ||
      rawFlt.toUpperCase().includes('TOTAL') ||
      rawFlt.toUpperCase().includes('GRAND')
    ) {
      continue;
    }

    if (!rawAirline && !rawFlt) continue;

    const rawOrig = getVal(columns.origin);
    const rawDest = getVal(columns.destination);
    const rawLeg = getVal(columns.movementType);
    const rawSibt = getVal(columns.sibt);
    const rawSobt = getVal(columns.sobt);
    const rawAibt = getVal(columns.aibt);
    const rawAobt = getVal(columns.aobt);

    const movementType = resolveMovementType(rawOrig, rawDest, rawLeg, rawSibt, rawSobt, baseAirport);

    let rawSchedTime = '';
    let rawActTime = '';

    if (movementType === 'Arrival') {
      rawSchedTime = rawSibt || getVal(columns.scheduledTime);
      rawActTime = rawAibt || getVal(columns.actualTime);
    } else {
      rawSchedTime = rawSobt || getVal(columns.scheduledTime);
      rawActTime = rawAobt || getVal(columns.actualTime);
    }

    if (!rawSchedTime && columns.sibtSobt !== undefined) {
      const parts = getVal(columns.sibtSobt).split(/\s+/);
      rawSchedTime = movementType === 'Arrival' ? parts[0] || '' : parts[1] || parts[0] || '';
    }
    if (!rawActTime && columns.aibtAobt !== undefined) {
      const parts = getVal(columns.aibtAobt).split(/\s+/);
      rawActTime = movementType === 'Arrival' ? parts[0] || '' : parts[1] || parts[0] || '';
    }

    const schedParsed = parseExcelTime(rawSchedTime);
    const actParsed = parseExcelTime(rawActTime);

    const scheduledTime = schedParsed ? schedParsed.timeStr : '00:00';
    const scheduledHour = schedParsed ? schedParsed.hour : 0;
    const actualTime = actParsed ? actParsed.timeStr : '';
    const actualHour = actParsed ? actParsed.hour : scheduledHour;

    const rawDateVal = columns.flightDate !== undefined ? row[columns.flightDate] : (rawSchedTime || fallbackDate);
    const flightDate = parseExcelDate(rawDateVal, fallbackDate);

    let delayMinutes = 0;
    let status: NormalizedFDRRecord['status'] = 'ON_TIME';

    if (!actualTime || actualTime === '-' || actualTime === 'N/A') {
      status = 'CANCELLED';
      delayMinutes = 0;
    } else {
      delayMinutes = calculateDelayMinutes(scheduledTime, actualTime);
      if (delayMinutes > 15) {
        status = 'DELAYED';
      } else if (delayMinutes < -15) {
        status = 'EARLY';
      } else {
        status = 'ON_TIME';
      }
    }

    const regNo = getVal(columns.aircraftReg) || 'N/A';
    const norm = normalizeAirline(rawAirline, rawFlt, regNo);
    const suffixRes = extractFlightSuffix(rawFlt);

    records.push({
      flightDate,
      airline: norm.cleanName,
      airlineRaw: rawAirline,
      airlineCode: norm.code,
      flightNumber: rawFlt || 'N/A',
      flightNoBase: suffixRes.base,
      flightSuffix: suffixRes.suffix,
      pairedNo: 'N/A',
      aircraftReg: regNo,
      aircraftType: getVal(columns.aircraftType) || 'N/A',
      movementType,
      legRaw: rawLeg,
      legSubtype: 'SCHED',
      origin: rawOrig || (movementType === 'Departure' ? baseAirport : 'N/A'),
      destination: rawDest || (movementType === 'Arrival' ? baseAirport : 'N/A'),
      scheduledTime,
      actualTime,
      scheduledHour,
      actualHour,
      delayMinutes,
      rawDelayMinutes: delayMinutes,
      status,
      realized: status !== 'CANCELLED',
      mtowTons: 0,
      capacity: 0,
      paxLoad: 0,
      loadFactorPercent: 0,
      adult: 0,
      child: 0,
      infant: 0,
      transit: 0,
      transfer: 0,
      divert: 0,
      missed: 0,
      operatingCrew: 0,
      extraCrew: 0,
      totalCrew: 0,
      umrohPax: 0,
      cargoKg: 0,
      baggageKg: 0,
      posKg: 0,
      totalPayloadKg: 0,
      stand: 'N/A',
      runway: 'N/A',
      isFinal: false,
      finalTime: 'N/A',
      branch: baseAirport,
    });
  }

  return records;
}

export function aggregateHourlySlotWaves(
  records: NormalizedFDRRecord[],
  timeBasis: 'actual' | 'scheduled' = 'actual'
): HourlySlotWavesSummary {
  const buckets: HourlySlotWavesBucket[] = [];

  for (let h = 0; h < 24; h++) {
    const hStr = String(h).padStart(2, '0');
    buckets.push({
      hour: h,
      hourLabel: `${hStr}:00`,
      timeRange: `${hStr}:00–${hStr}:59`,
      totalMovements: 0,
      arrivals: 0,
      departures: 0,
      onTime: 0,
      delayed: 0,
      early: 0,
      cancelled: 0,
      airlines: {},
    });
  }

  let totalMovements = 0;
  let totalArr = 0;
  let totalDep = 0;
  let totalOnTime = 0;
  let totalDelayed = 0;
  let totalEarly = 0;
  let totalCancelled = 0;

  for (const r of records) {
    const targetHour = timeBasis === 'actual' ? r.actualHour : r.scheduledHour;
    const safeHour = Math.max(0, Math.min(23, targetHour ?? 0));
    const b = buckets[safeHour];

    b.totalMovements++;
    totalMovements++;

    if (r.movementType === 'Arrival') {
      b.arrivals++;
      totalArr++;
    } else {
      b.departures++;
      totalDep++;
    }

    if (r.status === 'ON_TIME') {
      b.onTime++;
      totalOnTime++;
    } else if (r.status === 'DELAYED') {
      b.delayed++;
      totalDelayed++;
    } else if (r.status === 'EARLY') {
      b.early++;
      totalEarly++;
    } else if (r.status === 'CANCELLED' || r.status === 'HOLD_OR_CANCELLED') {
      b.cancelled++;
      totalCancelled++;
    }

    const al = r.airline || 'Unknown';
    b.airlines[al] = (b.airlines[al] || 0) + 1;
  }

  let peakHour = 0;
  let maxMovements = 0;
  for (let h = 0; h < 24; h++) {
    if (buckets[h].totalMovements > maxMovements) {
      maxMovements = buckets[h].totalMovements;
      peakHour = h;
    }
  }

  const otpPercentage = totalMovements > 0 ? Number(((totalOnTime / totalMovements) * 100).toFixed(1)) : 0;

  return {
    buckets,
    totalMovements,
    totalArrivals: totalArr,
    totalDepartures: totalDep,
    totalOnTime,
    totalDelayed,
    totalEarly,
    totalCancelled,
    peakHour: {
      hour: peakHour,
      timeRange: buckets[peakHour].timeRange,
      movements: maxMovements,
    },
    otpPercentage,
  };
}
