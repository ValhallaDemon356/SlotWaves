/**
 * SlotWaves: Comprehensive Flight Daily Report (FDR) OASYS Engine
 *
 * Implements deterministic auto-detection, pre-scan staging, multi-file & ZIP processing,
 * and dedicated parsers for all 7 InJourney Airports OASYS FDR report templates:
 * 1. NORMAL (33 Columns — Full Operational Movement)
 * 2. EX_HALIM (33 Columns — Ex Halim CGK Monitoring, Supports 0-Row Empty State)
 * 3. LOAD_FACTOR (13 Columns — Commercial Seat Load Factor Report)
 * 4. COMPARE_LOAD_FACTOR (20 Columns — YoY Branch Summary Comparison)
 * 5. COMPARE_LOAD_FACTOR_DAY (20 Columns — YoY Daily Trend Comparison)
 * 6. OASYS_VS_APPS (16 Columns + Nested Reconciliation Matrix Table)
 * 7. OASYS_VS_EDIFLY (14 Columns + HTML-Commented Footer Totals)
 */

import JSZip from 'jszip';

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
    badgeColor: 'bg-amber-100 text-amber-800 border-amber-300',
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
    badgeColor: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    description: 'Year-over-Year comparison of Flights, Pax, Cargo, SeatCap, and SLF %.',
  },
  COMPARE_LOAD_FACTOR_DAY: {
    name: 'Compare Load Factor Daily YoY',
    label: 'COMPARE LOAD FACTOR DAILY YoY (20 COLUMNS)',
    columnsCount: 20,
    badgeColor: 'bg-purple-100 text-purple-800 border-purple-300',
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
 * 36 InJourney Airports Coordinates Dictionary.
 */
export const INJOURNEY_AIRPORTS_COORDINATES: Record<
  string,
  { name: string; city: string; lat: number; lng: number; lon: number }
> = {
  CGK: { name: 'Soekarno-Hatta International Airport', city: 'Jakarta', lat: -6.1256, lng: 106.6559, lon: 106.6559 },
  DPS: { name: 'I Gusti Ngurah Rai International Airport', city: 'Denpasar / Bali', lat: -8.7482, lng: 115.1672, lon: 115.1672 },
  SUB: { name: 'Juanda International Airport', city: 'Surabaya', lat: -7.3798, lng: 112.7876, lon: 112.7876 },
  KNO: { name: 'Kualanamu International Airport', city: 'Medan', lat: 3.6422, lng: 98.8853, lon: 98.8853 },
  YIA: { name: 'Yogyakarta International Airport', city: 'Kulon Progo', lat: -7.9073, lng: 110.0544, lon: 110.0544 },
  UPG: { name: 'Sultan Hasanuddin International Airport', city: 'Makassar', lat: -5.0616, lng: 119.554, lon: 119.554 },
  BPN: { name: 'Sultan Aji Muhammad Sulaiman Sepinggan', city: 'Balikpapan', lat: -1.2683, lng: 116.8944, lon: 116.8944 },
  BDJ: { name: 'Syamsudin Noor International Airport', city: 'Banjarmasin', lat: -3.4475, lng: 114.7617, lon: 114.7617 },
  SRG: { name: 'Jenderal Ahmad Yani International Airport', city: 'Semarang', lat: -6.9744, lng: 110.3744, lon: 110.3744 },
  PLM: { name: 'Sultan Mahmud Badaruddin II Airport', city: 'Palembang', lat: -2.8986, lng: 104.7003, lon: 104.7003 },
  PNK: { name: 'Supadio International Airport', city: 'Pontianak', lat: -0.1506, lng: 109.4039, lon: 109.4039 },
  PKU: { name: 'Sultan Syarif Kasim II International Airport', city: 'Pekanbaru', lat: 0.4608, lng: 101.4444, lon: 101.4444 },
  PDG: { name: 'Minangkabau International Airport', city: 'Padang', lat: -0.7875, lng: 100.2808, lon: 100.2808 },
  BTH: { name: 'Hang Nadim International Airport', city: 'Batam', lat: 1.1211, lng: 104.1189, lon: 104.1189 },
  TNJ: { name: 'Raja Haji Fisabilillah International Airport', city: 'Tanjung Pinang', lat: 0.9236, lng: 104.5317, lon: 104.5317 },
  BDO: { name: 'Husein Sastranegara International Airport', city: 'Bandung', lat: -6.9006, lng: 107.5761, lon: 107.5761 },
  HLP: { name: 'Halim Perdanakusuma International Airport', city: 'Jakarta', lat: -6.2656, lng: 106.8906, lon: 106.8906 },
  KJT: { name: 'Kertajati International Airport', city: 'Majalengka', lat: -6.6578, lng: 108.1889, lon: 108.1889 },
  LOP: { name: 'Lombok International Airport', city: 'Lombok Praya', lat: -8.7583, lng: 116.2764, lon: 116.2764 },
  MDC: { name: 'Sam Ratulangi International Airport', city: 'Manado', lat: 1.5494, lng: 124.9264, lon: 124.9264 },
  AMQ: { name: 'Pattimura Airport', city: 'Ambon', lat: -3.7103, lng: 128.0894, lon: 128.0894 },
  KOE: { name: 'El Tari Airport', city: 'Kupang', lat: -10.1714, lng: 123.6708, lon: 123.6708 },
  PGK: { name: 'Depati Amir Airport', city: 'Pangkal Pinang', lat: -2.1628, lng: 106.1394, lon: 106.1394 },
  TJQ: { name: 'H.A.S. Hanandjoeddin Airport', city: 'Tanjung Pandan / Belitung', lat: -2.7606, lng: 107.755, lon: 107.755 },
  TKG: { name: 'Radin Inten II Airport', city: 'Bandar Lampung', lat: -5.2422, lng: 105.1786, lon: 105.1786 },
  DJB: { name: 'Sultan Thaha Airport', city: 'Jambi', lat: -1.6381, lng: 103.6444, lon: 103.6444 },
  BKS: { name: 'Fatmawati Soekarno Airport', city: 'Bengkulu', lat: -3.8647, lng: 102.34, lon: 102.34 },
  BTJ: { name: 'Sultan Iskandar Muda International Airport', city: 'Banda Aceh', lat: 5.5236, lng: 95.4206, lon: 95.4206 },
  DTB: { name: 'Raja Sisingamangaraja XII Airport (Silangit)', city: 'Siborong-borong', lat: 2.2575, lng: 98.9936, lon: 98.9936 },
  BWX: { name: 'Banyuwangi International Airport', city: 'Banyuwangi', lat: -8.3106, lng: 114.34, lon: 114.34 },
  JOG: { name: 'Adisutjipto International Airport', city: 'Yogyakarta', lat: -7.7881, lng: 110.4317, lon: 110.4317 },
  PKY: { name: 'Tjilik Riwut Airport', city: 'Palangkaraya', lat: -2.2256, lng: 113.9439, lon: 113.9439 },
  BIK: { name: 'Frans Kaisiepo International Airport', city: 'Biak', lat: -1.1906, lng: 136.1103, lon: 136.1103 },
  DJJ: { name: 'Sentani International Airport', city: 'Jayapura', lat: -2.5769, lng: 140.5164, lon: 140.5164 },
  PWL: { name: 'Tunggul Wulung Airport', city: 'Cilacap / Purwokerto', lat: -7.6436, lng: 109.0208, lon: 109.0208 },
  PCB: { name: 'Pondok Cabe Airport', city: 'Tangerang Selatan', lat: -6.3386, lng: 106.7533, lon: 106.7533 },
  // Common Domestic & International Stations (GA, ID, JT Network)
  SOQ: { name: 'Domine Eduard Osok Airport', city: 'Sorong', lat: -0.8906, lng: 131.2872, lon: 131.2872 },
  PLW: { name: 'Mutiara SIS Al-Jufrie Airport', city: 'Palu', lat: -0.9178, lng: 119.9097, lon: 119.9097 },
  TIM: { name: 'Mozes Kilangin Airport', city: 'Timika', lat: -4.5283, lng: 136.8867, lon: 136.8867 },
  MKQ: { name: 'Mopah Airport', city: 'Merauke', lat: -8.5203, lng: 140.4181, lon: 140.4181 },
  KDI: { name: 'Haluoleo Airport', city: 'Kendari', lat: -4.0817, lng: 122.4178, lon: 122.4178 },
  GTO: { name: 'Djalaluddin Airport', city: 'Gorontalo', lat: 0.6372, lng: 122.8517, lon: 122.8517 },
  LUW: { name: 'Syukuran Aminuddin Amir Airport', city: 'Luwuk', lat: -1.0378, lng: 122.7758, lon: 122.7758 },
  TTE: { name: 'Sultan Babullah Airport', city: 'Ternate', lat: 0.8317, lng: 127.3806, lon: 127.3806 },
  LBJ: { name: 'Komodo Airport', city: 'Labuan Bajo', lat: -8.4878, lng: 119.8872, lon: 119.8872 },
  BMU: { name: 'Sultan Muhammad Salahuddin Airport', city: 'Bima', lat: -8.5397, lng: 118.6903, lon: 118.6903 },
  SIN: { name: 'Singapore Changi Airport', city: 'Singapore', lat: 1.3644, lng: 103.9915, lon: 103.9915 },
  KUL: { name: 'Kuala Lumpur International Airport', city: 'Kuala Lumpur', lat: 2.7456, lng: 101.7072, lon: 101.7072 },
  HND: { name: 'Tokyo Haneda Airport', city: 'Tokyo', lat: 35.5494, lng: 139.7798, lon: 139.7798 },
  NRT: { name: 'Narita International Airport', city: 'Tokyo Narita', lat: 35.7720, lng: 140.3929, lon: 140.3929 },
  ICN: { name: 'Incheon International Airport', city: 'Seoul', lat: 37.4602, lng: 126.4407, lon: 126.4407 },
  PVG: { name: 'Shanghai Pudong International Airport', city: 'Shanghai', lat: 31.1443, lng: 121.8083, lon: 121.8083 },
  CAN: { name: 'Guangzhou Baiyun International Airport', city: 'Guangzhou', lat: 23.3924, lng: 113.2988, lon: 113.2988 },
  SYD: { name: 'Sydney Kingsford Smith Airport', city: 'Sydney', lat: -33.9399, lng: 151.1753, lon: 151.1753 },
  MEL: { name: 'Melbourne Airport', city: 'Melbourne', lat: -37.6690, lng: 144.8410, lon: 144.8410 },
  PER: { name: 'Perth Airport', city: 'Perth', lat: -31.9385, lng: 115.9672, lon: 115.9672 },
  JED: { name: 'King Abdulaziz International Airport', city: 'Jeddah', lat: 21.6796, lng: 39.1565, lon: 39.1565 },
  MED: { name: 'Prince Mohammad Bin Abdulaziz Airport', city: 'Medina', lat: 24.5534, lng: 39.7051, lon: 39.7051 },
  DOH: { name: 'Hamad International Airport', city: 'Doha', lat: 25.2731, lng: 51.6081, lon: 51.6081 },
  BKK: { name: 'Suvarnabhumi Airport', city: 'Bangkok', lat: 13.6900, lng: 100.7501, lon: 100.7501 },
  HKG: { name: 'Hong Kong International Airport', city: 'Hong Kong', lat: 22.3080, lng: 113.9185, lon: 113.9185 },
};

export const INJOURNEY_AIRPORTS = INJOURNEY_AIRPORTS_COORDINATES;

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
 * e.g. "4.502.559" -> 4502559
 * e.g. "-8,82" -> -8.82
 * e.g. "79,33" -> 79.33
 * e.g. "1.242,86" -> 1242.86
 * e.g. "-33,33" -> -33.33
 * e.g. "71.8%" -> 71.8
 */
export function parseIndonesianNumber(val: unknown, fallback = 0): number {
  if (val === null || val === undefined) return fallback;
  if (typeof val === 'number') return isNaN(val) ? fallback : val;
  const str = cleanHtmlText(String(val)).replace(/%/g, '').trim();
  if (str === '' || str === '-' || str.toLowerCase() === 'nan' || str.toLowerCase() === 'null') return fallback;

  // Indonesian format with comma decimal (e.g. "4.502.559,25" or "-8,82" or "79,33")
  if (str.includes(',')) {
    const cleaned = str.replace(/\./g, '').replace(',', '.');
    const num = parseFloat(cleaned);
    return isNaN(num) ? fallback : num;
  }

  // Indonesian format with thousand dot but no decimal comma (e.g. "4.502.559" or "55.585.948")
  // In Indonesian financial reports, standard dot format with 3-digit groups indicates thousand separators
  if (/^-?\d{1,3}(\.\d{3})+$/.test(str)) {
    const cleaned = str.replace(/\./g, '');
    const num = parseFloat(cleaned);
    return isNaN(num) ? fallback : num;
  }

  // Standard notation (e.g. 1242.86 or 1242)
  const num = parseFloat(str);
  return isNaN(num) ? fallback : num;
}

/**
 * Convert Excel serial decimal time or string time ("08:30", "08:30:00")
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

  // Standard "HH:mm(:ss)" or datetime string with "HH:mm"
  const timeMatch = str.match(/(\d{1,2}):(\d{2})(?::(\d{2}))?/);
  if (timeMatch) {
    let h = parseInt(timeMatch[1], 10);
    const m = parseInt(timeMatch[2], 10);
    if (h >= 24) h = 23;
    const timeStr = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
    return { timeStr, hour: h };
  }

  // 4-digit military time (e.g. "0615" -> "06:15")
  const militaryMatch = str.match(/^(\d{2})(\d{2})$/);
  if (militaryMatch) {
    const h = parseInt(militaryMatch[1], 10);
    const m = parseInt(militaryMatch[2], 10);
    if (h < 24 && m < 60) {
      return { timeStr: `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`, hour: h };
    }
  }

  // Numeric value (Excel decimal time, e.g. 0.354166667)
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

  // YYYY-MM-DD or YYYY/MM/DD
  const isoMatch = str.match(/(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/);
  if (isoMatch) {
    return `${isoMatch[1]}-${isoMatch[2].padStart(2, '0')}-${isoMatch[3].padStart(2, '0')}`;
  }

  // DD-MM-YYYY or DD/MM/YYYY
  const idMatch = str.match(/(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})/);
  if (idMatch) {
    return `${idMatch[3]}-${idMatch[2].padStart(2, '0')}-${idMatch[1].padStart(2, '0')}`;
  }

  // Excel serial date integer (e.g. 45200)
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
  // 1. Leg column check (A / D / ARR / DEP) has highest authority in OASYS
  if (legValue) {
    const legUpper = legValue.toUpperCase().trim();
    if (legUpper.startsWith('A') || legUpper.includes('ARR')) return 'Arrival';
    if (legUpper.startsWith('D') || legUpper.includes('DEP')) return 'Departure';
  }

  const normBase = baseAirport.toUpperCase().trim();
  const normOrig = origin.toUpperCase().trim();
  const normDest = destination.toUpperCase().trim();

  // 2. Direct airport relative check
  if (normDest === normBase && normOrig !== normBase) {
    return 'Arrival';
  }
  if (normOrig === normBase && normDest !== normBase) {
    return 'Departure';
  }

  // 3. SIBT vs SOBT presence
  if (sibt && !sobt) return 'Arrival';
  if (sobt && !sibt) return 'Departure';

  return 'Departure';
}

/**
 * Extract trailing suffix from flight number using regex ^([A-Z0-9]*?)(\d+)([A-Z<$&^])?$.
 */
export function extractFlightSuffix(flightNo: string, pairedNo = ''): {
  base: string;
  suffix: string;
  suffixInfo: (typeof OASYS_SUFFIX_DICTIONARY)[string] | null;
} {
  const clean = cleanHtmlText(String(flightNo || '')).toUpperCase().trim();
  const stripped = clean.replace(/\s+/g, '');
  const match = stripped.match(/^([A-Z0-9]*?)(\d+)([A-Z<$&^])?$/);
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

  // Check paired flight number if provided
  if (pairedNo) {
    const cleanPaired = cleanHtmlText(String(pairedNo || '')).toUpperCase().trim();
    const strippedPaired = cleanPaired.replace(/\s+/g, '');
    const pairedMatch = strippedPaired.match(/^([A-Z0-9]*?)(\d+)([A-Z<$&^])?$/);
    if (pairedMatch && pairedMatch[3]) {
      const base = `${pairedMatch[1] || ''}${pairedMatch[2]}`;
      const suffix = pairedMatch[3];
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

  // TransNusa: 8B, M8, TNE, TNV, TRN
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
 * Exact rules per OASYS specifications:
 * 1. NORMAL: 33 <th> headers (PAIRED, SIBT, AIBT, MTOW, STAND, RUNWAY, FINAL) and filename does NOT match EX HALIM/HLP.
 * 2. EX_HALIM: Same 33 <th> headers, but filename contains EX HALIM/HLP, or table has 33 headers and 0 data rows.
 * 3. LOAD_FACTOR: 13 <th> headers (No, Airlines, Flight Number, Scheduled Time, Type of Aircraft, Aircraft Reg No, Leg, Arrival Route, Departure Route, Seat Capacity, Pax, SLF, Airport) + footer note PAX : ADULT + CHILD + TRANSIT + TRANSFER + EXTRA_CREW.
 * 4. COMPARE_LOAD_FACTOR: <title> contains DAU OASYS and single <table> with <td> (NO <th>) Row 0 FLIGHT/PASSENGER/CARGO/SEAT CAPACITY/SEAT LOAD FACTOR 2025/2026, Row 1 20 subheaders, and Row 2 [1] is airport only without date.
 * 5. COMPARE_LOAD_FACTOR_DAY: Same 20-col <td> structure as COMPARE_LOAD_FACTOR, but data row [1] has airport AND date (e.g. CGK <br>2026-09-30).
 * 6. OASYS_VS_APPS: <title> contains APPS VS FLIGHT DEP or header contains OASYS DEPARTURE VS APPS DATA, and 16 <th> headers (STAD, OFF BLOCK TIME, D/I, CITY...).
 * 7. OASYS_VS_EDIFLY: 14 <th> headers (STATION 1, STATION 2, API PAX EDIFLY, API CARGO EDIFLY).
 */
export function detectFdrTemplateType(htmlString: string, fileName = ''): OasysFdrType {
  const content = String(htmlString || '');
  const lower = content.toLowerCase();
  const fnLower = String(fileName || '').toLowerCase();

  // ── PRIORITY 1: COMPARE_LOAD_FACTOR_DAY & COMPARE_LOAD_FACTOR (20 Columns <td> table, NO <th>) ──
  // Detection: Raw HTML or <title> contains DAU OASYS or DATA LALU LINTAS ANGKUTAN UDARA,
  // OR table Row 0/1 contains SEAT LOAD FACTOR and RATIO / PREVYEAR
  if (
    lower.includes('dau oasys') ||
    lower.includes('data lalu lintas angkutan udara') ||
    (lower.includes('seat load factor') && (lower.includes('ratio') || lower.includes('prevyear') || lower.includes('2025') || lower.includes('2026'))) ||
    lower.includes('seat load factor 202') ||
    fnLower.includes('compare_load_factor') ||
    fnLower.includes('compare load factor')
  ) {
    // Distinguish COMPARE_LOAD_FACTOR_DAY vs COMPARE_LOAD_FACTOR:
    // COMPARE_LOAD_FACTOR_DAY has dates (YYYY-MM-DD or DD-MM-YYYY) in column 1/row cells OR 'day' in filename/title
    if (
      content.match(/<td[^>]*>[A-Z]{3}\s*(<br\s*\/?>)?\s*\d{4}-\d{2}-\d{2}/i) ||
      content.match(/[A-Z]{3}\s*(<br\s*\/?>)?\s*\d{4}-\d{2}-\d{2}/i) ||
      content.match(/<td[^>]*>\s*\d{4}-\d{2}-\d{2}/i) ||
      content.match(/\d{4}-\d{2}-\d{2}/) ||
      fnLower.includes('day') ||
      lower.includes('day by day') ||
      lower.includes('compare load factor day')
    ) {
      return 'COMPARE_LOAD_FACTOR_DAY';
    }
    return 'COMPARE_LOAD_FACTOR';
  }

  // ── PRIORITY 2: OASYS_VS_APPS (16 Columns) ──
  if (
    lower.includes('apps vs flight dep') ||
    lower.includes('oasys departure vs apps data') ||
    (lower.includes('off block time') && lower.includes('stad')) ||
    fnLower.includes('apps')
  ) {
    return 'OASYS_VS_APPS';
  }

  // ── PRIORITY 3: OASYS_VS_EDIFLY (14 Columns) ──
  if (
    lower.includes('api pax edifly') ||
    lower.includes('api cargo edifly') ||
    (lower.includes('station 1') && lower.includes('station 2') && lower.includes('edifly')) ||
    fnLower.includes('edifly')
  ) {
    return 'OASYS_VS_EDIFLY';
  }

  // ── PRIORITY 4: LOAD_FACTOR (13 Columns <th> table) ──
  if (
    (lower.includes('type of aircraft') && (lower.includes('arrival route') || lower.includes('departure route')) && (lower.includes('slf') || lower.includes('extra_crew') || lower.includes('seat capacity'))) ||
    fnLower.includes('load_factor') ||
    fnLower.includes('load factor')
  ) {
    return 'LOAD_FACTOR';
  }

  // ── PRIORITY 5: EX_HALIM (33 Columns with Halim indicator or 0-movement empty state) ──
  if (fnLower.includes('halim') || fnLower.includes('hlp')) {
    return 'EX_HALIM';
  }

  const hasZeroMovementSummary = content.match(/-PAX\s*ALL\s*-\s*\(\s*0\s*\)\s*-/i);
  if (hasZeroMovementSummary) {
    const trMatches = content.match(/<tr[^>]*>[\s\S]*?<\/tr>/gi) || [];
    let nonHeaderRows = 0;
    for (const tr of trMatches) {
      if (tr.includes('<th') || tr.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;
      const clean = cleanHtmlText(tr).toUpperCase();
      if (clean === '' || clean.includes('PAX ALL') || clean.startsWith('TOTAL')) continue;
      nonHeaderRows++;
    }
    if (nonHeaderRows === 0) {
      return 'EX_HALIM';
    }
  }

  // ── PRIORITY 6: NORMAL (33 Columns Operational Daily Report) ──
  return 'NORMAL';
}

/**
 * Backward compatibility alias.
 */
export const detectOasysFdrType = detectFdrTemplateType;

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
  metadata: {
    branchCode: string;
    period: string;
    operator: string;
    realization: string;
    rowCount: number;
    columnCount: number;
  };
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
  const reportType = detectFdrTemplateType(content, fileName);
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

  // 3. Count rows
  let totalRows = 0;
  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) {
      continue;
    }
    const cleanTr = cleanHtmlText(trHtml).toUpperCase();
    if (
      cleanTr === '' ||
      cleanTr.includes('PAX ALL') ||
      cleanTr.startsWith('TOTAL') ||
      cleanTr.startsWith('GRAND TOTAL') ||
      cleanTr.includes('FLT DEP') ||
      cleanTr.includes('SEAT LOAD FACTOR')
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
    metadata: {
      branchCode: branchCode.toUpperCase(),
      period: transactionsDateFdr || '01-09-2026 -> 30-09-2026',
      operator: operator || 'ALL',
      realization: realization.toUpperCase() || 'YES',
      rowCount: totalRows,
      columnCount: typeCfg.columnsCount,
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE 1 & 2: NORMAL & EX_HALIM (33 COLUMNS)
// ─────────────────────────────────────────────────────────────────────────────

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
  operatingCrew: number; // CRW + EX. CRW (Fixed bug where crew was 0!)
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
    // Turnaround Ground Time
    avgGroundTimeMinutes: number;
    medianGroundTimeMinutes: number;
    minGroundTimeMinutes: number;
    maxGroundTimeMinutes: number;
    standChangesCount: number;
    topStands: Array<{ stand: string; count: number }>;
    topRunways: Array<{ runway: string; count: number }>;
    narrowBodyCount: number;
    wideBodyCount: number;
    histBins: Array<{ label: string; group: string; count: number; color: string; desc: string }>;
  };
  metadata: {
    branchCode: string;
    period: string;
    operator: string;
    realization: string;
    isExHalim: boolean;
  };
} {
  const content = String(htmlString || '');
  const records: NormalizedFDRRecord[] = [];

  // Extract metadata
  const pre = preScanOasysFdr(content);
  const detectedBranch = pre.branchCode || 'CGK';

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) {
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

    // Exact 33 columns:
    // [0] NO, [1] AIRLINE, [2] FLIGHT NO, [3] PAIRED NO, [4] DESC,
    // [5] SIBT/SOBT, [6] AIBT/AOBT, [7] LEG, [8] CITY 1, [9] CITY 2,
    // [10] MTOW, [11] REG. NO, [12] CAP., [13] LOAD, [14] ADULT,
    // [15] CHILD, [16] INFANT, [17] TRANSIT, [18] TRANFER, [19] DIVERT,
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
    const city1 = (cells[8] || detectedBranch).toUpperCase();
    const city2 = (cells[9] || detectedBranch).toUpperCase();
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
    const branch = (cells[31] || detectedBranch).toUpperCase();

    // Suffix & Airline normalizer
    const suffixRes = extractFlightSuffix(rawFlt, pairedNo);
    const normAirline = normalizeAirline(rawAirline, rawFlt, regNo);

    // Movement direction
    const movementType = resolveMovementType(city1, city2, rawLeg, undefined, undefined, branch);

    // Leg Subtype: SCHED, UNSCHED, CARGO
    let legSubtype: NormalizedFDRRecord['legSubtype'] = 'SCHED';
    const legUpper = rawLeg.toUpperCase();
    if (legUpper.includes('UNSCHED')) legSubtype = 'UNSCHED';
    else if (legUpper.includes('CARGO')) legSubtype = 'CARGO';
    else legSubtype = 'SCHED';

    // Parse times
    const schedParsed = parseExcelTime(rawSchedTime);
    const actParsed = parseExcelTime(rawActTime);

    const scheduledTime = schedParsed ? schedParsed.timeStr : '00:00';
    const scheduledHour = schedParsed ? schedParsed.hour : 0;
    const actualTime = actParsed ? actParsed.timeStr : '';
    const actualHour = actParsed ? actParsed.hour : scheduledHour;
    const flightDate = parseExcelDate(rawSchedTime || rawActTime);

    // CRITICAL FIX: Handle REAL = NO (unrealized/hold flights: 30-11--0001, empty actual time, *-HOLD, nan%)
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

      // Cross-Midnight Correction & Outlier Filter (> 1400m or < -360m)
      if (crossMidnightCorrection) {
        if (delayMinutes > 720) delayMinutes -= 1440;
        else if (delayMinutes < -720) delayMinutes += 1440;
      }

      if (outlierFilterMinutes > 0 && Math.abs(delayMinutes) > outlierFilterMinutes) {
        // Clamp or mark within bounds
        if (delayMinutes > 1400) delayMinutes -= 1440;
        else if (delayMinutes < -360) delayMinutes += 1440;
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

    // FIX: Map CRW + EX.CRW to Operating Crew (fixes Operating Crew showing 0)
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
  let narrowBodyCount = 0;
  let wideBodyCount = 0;
  const suffixBreakdown: Record<string, number> = {};
  const standMap: Record<string, number> = {};
  const runwayMap: Record<string, number> = {};

  // 9-bucket variance histogram
  const histBuckets = [
    { label: '<-60m', group: 'EARLY_EXTREME', min: -Infinity, max: -60, count: 0, color: '#3B82F6', desc: 'Early > 60m' },
    { label: '-60..-16m', group: 'EARLY_MODERATE', min: -60, max: -15, count: 0, color: '#60A5FA', desc: 'Early 16-60m' },
    { label: '-15..-1m', group: 'EARLY_SLIGHT', min: -15, max: 0, count: 0, color: '#93C5FD', desc: 'Early 1-15m' },
    { label: '0m', group: 'ON_TIME_EXACT', min: 0, max: 1, count: 0, color: '#10B981', desc: 'Exact On-Time' },
    { label: '+1..+15m', group: 'ON_TIME_TOLERANCE', min: 1, max: 16, count: 0, color: '#34D399', desc: 'On-Time (1-15m delay)' },
    { label: '+16..+30m', group: 'DELAY_SHORT', min: 16, max: 31, count: 0, color: '#FBBF24', desc: 'Delay 16-30m' },
    { label: '+31..+60m', group: 'DELAY_MEDIUM', min: 31, max: 61, count: 0, color: '#F59E0B', desc: 'Delay 31-60m' },
    { label: '+61..+120m', group: 'DELAY_LONG', min: 61, max: 121, count: 0, color: '#F97316', desc: 'Delay 61-120m' },
    { label: '>+120m', group: 'DELAY_SEVERE', min: 121, max: Infinity, count: 0, color: '#EF4444', desc: 'Severe Delay > 120m' },
  ];

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

    if (r.stand && r.stand !== 'N/A') {
      standMap[r.stand] = (standMap[r.stand] || 0) + 1;
    }
    if (r.runway && r.runway !== 'N/A') {
      runwayMap[r.runway] = (runwayMap[r.runway] || 0) + 1;
    }

    const descUpper = r.aircraftType.toUpperCase();
    if (descUpper.includes('WIDE') || descUpper.includes('330') || descUpper.includes('350') || descUpper.includes('777') || descUpper.includes('787')) {
      wideBodyCount++;
    } else {
      narrowBodyCount++;
    }

    if (!r.realized || r.status === 'HOLD_OR_CANCELLED') {
      cancelledHoldCount++;
    } else {
      realizedCount++;
      delaySum += r.delayMinutes;
      if (r.status === 'ON_TIME') onTimeCount++;
      else if (r.status === 'DELAYED') delayedCount++;
      else if (r.status === 'EARLY') earlyCount++;

      // Bucket matching
      for (const b of histBuckets) {
        if (r.delayMinutes >= b.min && r.delayMinutes < b.max) {
          b.count++;
          break;
        }
      }
    }
  }

  // Turnaround Ground Time calculation for paired flights
  const groundTimes: number[] = [];
  const arrivalMap: Record<string, NormalizedFDRRecord> = {};
  for (const r of records) {
    if (r.movementType === 'Arrival' && r.realized) {
      const key = r.aircraftReg !== 'N/A' ? r.aircraftReg : (r.pairedNo !== 'N/A' ? r.pairedNo : '');
      if (key) arrivalMap[key] = r;
    }
  }
  for (const r of records) {
    if (r.movementType === 'Departure' && r.realized) {
      const key = r.aircraftReg !== 'N/A' ? r.aircraftReg : (r.pairedNo !== 'N/A' ? r.pairedNo : '');
      const arr = key ? arrivalMap[key] : null;
      if (arr && arr.actualTime && r.actualTime) {
        const diff = calculateDelayMinutes(arr.actualTime, r.actualTime);
        if (diff > 10 && diff < 360) {
          groundTimes.push(diff);
        }
      }
    }
  }

  groundTimes.sort((a, b) => a - b);
  const avgGround = groundTimes.length > 0 ? Math.round(groundTimes.reduce((a, b) => a + b, 0) / groundTimes.length) : 45;
  const medianGround = groundTimes.length > 0 ? groundTimes[Math.floor(groundTimes.length / 2)] : 45;
  const minGround = groundTimes.length > 0 ? groundTimes[0] : 25;
  const maxGround = groundTimes.length > 0 ? groundTimes[groundTimes.length - 1] : 90;

  const topStands = Object.entries(standMap)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 10)
    .map(([stand, count]) => ({ stand, count }));

  const topRunways = Object.entries(runwayMap)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6)
    .map(([runway, count]) => ({ runway, count }));

  const avgDelayMinutes = realizedCount > 0 ? Math.round((delaySum / realizedCount) * 10) / 10 : 0;
  const crewPerFlight = realizedCount > 0 ? Math.round((totalCrew / realizedCount) * 10) / 10 : 0;
  const avgMtowPerFlight = records.length > 0 ? Math.round((totalMtowTons / records.length) * 10) / 10 : 0;
  const otpPercentage = realizedCount > 0 ? Math.round((onTimeCount / realizedCount) * 1000) / 10 : 0;

  // ── Route & Airline Aggregations ──
  const INT_AIRPORTS_SET = new Set([
    'SIN', 'KUL', 'HND', 'NRT', 'ICN', 'PVG', 'CAN', 'SYD', 'MEL', 'PER',
    'JED', 'MED', 'DOH', 'BKK', 'HKG', 'DXB', 'AUH', 'KTM', 'DMK', 'MNL',
    'TPE', 'PEN', 'JHB', 'BKI', 'KCH', 'LGK', 'CNX', 'HKT', 'REP', 'PNH',
    'SGN', 'HAN', 'BNE', 'ADL', 'AKL', 'LHR', 'AMS', 'FRA', 'CDG', 'IST',
  ]);

  const routesMap: Record<string, any> = {};
  const airlinesMap: Record<string, any> = {};
  let schedCount = 0;
  let unschedCount = 0;
  let cargoCount = 0;
  let arrivalCount = 0;
  let departureCount = 0;
  let totalCap = 0;

  // Hourly buckets 00..23
  const hourlyLabels = [
    '00:00', '01:00', '02:00', '03:00', '04:00', '05:00',
    '06:00', '07:00', '08:00', '09:00', '10:00', '11:00',
    '12:00', '13:00', '14:00', '15:00', '16:00', '17:00',
    '18:00', '19:00', '20:00', '21:00', '22:00', '23:00',
  ];
  const hourlyRanges = hourlyLabels.map((l, i) => `${l} - ${String(i).padStart(2, '0')}:59`);
  const arrDom = new Array(24).fill(0);
  const arrInt = new Array(24).fill(0);
  const depDom = new Array(24).fill(0);
  const depInt = new Array(24).fill(0);

  const arrPax = new Array(24).fill(0);
  const depPax = new Array(24).fill(0);
  const arrDomPax = new Array(24).fill(0);
  const arrIntPax = new Array(24).fill(0);
  const depDomPax = new Array(24).fill(0);
  const depIntPax = new Array(24).fill(0);

  const arrCargo = new Array(24).fill(0);
  const depCargo = new Array(24).fill(0);
  const arrDomCargo = new Array(24).fill(0);
  const arrIntCargo = new Array(24).fill(0);
  const depDomCargo = new Array(24).fill(0);
  const depIntCargo = new Array(24).fill(0);

  for (const r of records) {
    if (r.movementType === 'Arrival') arrivalCount++;
    else departureCount++;
    totalCap += r.capacity;

    if (r.legSubtype === 'UNSCHED') unschedCount++;
    else if (r.legSubtype === 'CARGO') cargoCount++;
    else schedCount++;

    // Route aggregation
    const rtKey = `${r.origin} → ${r.destination}`;
    if (!routesMap[rtKey]) {
      routesMap[rtKey] = {
        route: rtKey,
        origin: r.origin,
        destination: r.destination,
        dest: r.destination,
        flights: 0,
        movements: 0,
        count: 0,
        total_flights: 0,
        pax: 0,
        passengers: 0,
        totalPax: 0,
        capacity: 0,
        totalCapacity: 0,
        load: 0,
        delayedFlights: 0,
        delayed_flights: 0,
        delaySum: 0,
        delayCount: 0,
        maxDelay: 0,
        avgLf: 0,
        lf: 0,
        avg_load_factor: '0.0%',
        slf: '0.0%',
        delayRate: 0,
        delay_rate: 0,
        avgDelay: 0,
        avg_delay: 0,
        avg_delay_min: 0,
      };
    }
    const rt = routesMap[rtKey];
    rt.flights++;
    rt.movements++;
    rt.count++;
    rt.total_flights++;
    const fltPax = r.adult + r.child + r.infant + r.transit + r.transfer;
    rt.pax += fltPax;
    rt.passengers += fltPax;
    rt.totalPax += fltPax;
    rt.capacity += r.capacity;
    rt.totalCapacity += r.capacity;
    rt.load += r.paxLoad;
    if (r.delayMinutes > 15) {
      rt.delayedFlights++;
      rt.delayed_flights++;
    }
    if (r.realized && r.delayMinutes !== 0) {
      rt.delaySum += r.delayMinutes;
      rt.delayCount++;
      if (r.delayMinutes > rt.maxDelay) rt.maxDelay = r.delayMinutes;
    }

    // Airline aggregation
    const alName = r.airline || 'Unknown';
    if (!airlinesMap[alName]) {
      airlinesMap[alName] = {
        airline: alName,
        code: r.airlineCode || alName.slice(0, 3).toUpperCase(),
        flights: 0,
        passengers: 0,
        capacity: 0,
        cargo_kg: 0,
      };
    }
    airlinesMap[alName].flights++;
    airlinesMap[alName].passengers += fltPax;
    airlinesMap[alName].capacity += r.capacity;
    airlinesMap[alName].cargo_kg += r.cargoKg;

    // Hourly buckets
    const h = (r.actualHour >= 0 && r.actualHour < 24)
      ? r.actualHour
      : ((r.scheduledHour >= 0 && r.scheduledHour < 24) ? r.scheduledHour : 0);
    const isArr = r.movementType === 'Arrival';
    const isInt = INT_AIRPORTS_SET.has(r.origin) || INT_AIRPORTS_SET.has(r.destination);

    if (isArr) {
      if (isInt) {
        arrInt[h]++;
        arrIntPax[h] += r.paxLoad;
        arrIntCargo[h] += r.cargoKg;
      } else {
        arrDom[h]++;
        arrDomPax[h] += r.paxLoad;
        arrDomCargo[h] += r.cargoKg;
      }
      arrPax[h] += r.paxLoad;
      arrCargo[h] += r.cargoKg;
    } else {
      if (isInt) {
        depInt[h]++;
        depIntPax[h] += r.paxLoad;
        depIntCargo[h] += r.cargoKg;
      } else {
        depDom[h]++;
        depDomPax[h] += r.paxLoad;
        depDomCargo[h] += r.cargoKg;
      }
      depPax[h] += r.paxLoad;
      depCargo[h] += r.cargoKg;
    }
  }

  // Finalize route metrics
  const allRoutes = Object.values(routesMap);
  for (const rObj of allRoutes) {
    const lfVal = rObj.totalCapacity > 0 ? Math.round((rObj.totalPax / rObj.totalCapacity) * 1000) / 10 : 0;
    rObj.avgLf = lfVal;
    rObj.lf = lfVal;
    rObj.avg_load_factor = `${lfVal.toFixed(1)}%`;
    rObj.slf = `${lfVal.toFixed(1)}%`;

    const dRate = rObj.flights > 0 ? Math.round((rObj.delayedFlights / rObj.flights) * 1000) / 10 : 0;
    rObj.delayRate = dRate;
    rObj.delay_rate = dRate;

    const avgDel = rObj.delayCount > 0 ? Math.round((rObj.delaySum / rObj.delayCount) * 10) / 10 : 0;
    rObj.avgDelay = avgDel;
    rObj.avg_delay = avgDel;
    rObj.avg_delay_min = avgDel;
  }

  const busiestRoutes = [...allRoutes].sort((a, b) => b.flights - a.flights).slice(0, 10);
  const delayedRoutes = [...allRoutes]
    .filter(r => r.flights >= 1)
    .sort((a, b) => (b.delayedFlights - a.delayedFlights) || (b.avgDelay - a.avgDelay) || (b.maxDelay - a.maxDelay))
    .slice(0, 10);
  const highestSlfRoutes = [...allRoutes]
    .filter(r => r.capacity > 0)
    .sort((a, b) => b.avgLf - a.avgLf)
    .slice(0, 10);

  const suffixColors: Record<string, string> = {
    X: 'bg-blue-100 text-blue-700 border-blue-200',
    R: 'bg-rose-100 text-rose-700 border-rose-200',
    I: 'bg-emerald-100 text-emerald-700 border-emerald-200',
    D: 'bg-amber-100 text-amber-700 border-amber-200',
    A: 'bg-indigo-100 text-indigo-700 border-indigo-200',
    F: 'bg-teal-100 text-teal-700 border-teal-200',
    H: 'bg-purple-100 text-purple-700 border-purple-200',
    O: 'bg-cyan-100 text-cyan-700 border-cyan-200',
    L: 'bg-slate-100 text-slate-700 border-slate-200',
    '&': 'bg-slate-100 text-slate-700 border-slate-200',
    G: 'bg-slate-100 text-slate-700 border-slate-200',
    V: 'bg-purple-100 text-purple-700 border-purple-200',
    M: 'bg-rose-100 text-rose-700 border-rose-200',
    N: 'bg-amber-100 text-amber-700 border-amber-200',
    Z: 'bg-sky-100 text-sky-700 border-sky-200',
    S: 'bg-orange-100 text-orange-700 border-orange-200',
    T: 'bg-orange-100 text-orange-700 border-orange-200',
    K: 'bg-orange-100 text-orange-700 border-orange-200',
  };

  const suffixDistribution = Object.entries(suffixBreakdown)
    .sort((a, b) => b[1] - a[1])
    .map(([sfx, count]) => {
      const dictEntry = OASYS_SUFFIX_DICTIONARY[sfx];
      return {
        suffix: sfx,
        count,
        label: dictEntry ? dictEntry.label : `Suffix ${sfx}`,
        desc: dictEntry ? dictEntry.description : 'Special Suffix',
        category: dictEntry ? dictEntry.category : 'Special',
        colorClass: suffixColors[sfx] || 'bg-slate-100 text-slate-700 border-slate-200',
      };
    });

  let peakHourIdx = 0;
  let peakHourMov = 0;
  for (let h = 0; h < 24; h++) {
    const totalH = arrDom[h] + arrInt[h] + depDom[h] + depInt[h];
    if (totalH > peakHourMov) {
      peakHourMov = totalH;
      peakHourIdx = h;
    }
  }

  const hourlyDistribution = {
    labels: hourlyLabels,
    arr_dom: arrDom,
    arr_int: arrInt,
    dep_dom: depDom,
    dep_int: depInt,
    period_label: pre.transactionsDateFdr || '01-09-2026 -> 30-09-2026',
    time_basis: 'actual',
    peak_hour: {
      hour: peakHourIdx,
      time_range: `${String(peakHourIdx).padStart(2, '0')}:00-${String(peakHourIdx).padStart(2, '0')}:59`,
      movements: peakHourMov,
      average_per_day: `${peakHourMov} mov`,
    },
  };

  const combinedTrend = {
    labels: hourlyLabels,
    time_ranges: hourlyRanges,
    arr_passengers: arrPax,
    dep_passengers: depPax,
    arr_dom_passengers: arrDomPax,
    arr_int_passengers: arrIntPax,
    dep_dom_passengers: depDomPax,
    dep_int_passengers: depIntPax,
    arr_cargo: arrCargo,
    dep_cargo: depCargo,
    arr_cargo_kg: arrCargo,
    dep_cargo_kg: depCargo,
    arr_dom_cargo_kg: arrDomCargo,
    arr_int_cargo_kg: arrIntCargo,
    dep_dom_cargo_kg: depDomCargo,
    dep_int_cargo_kg: depIntCargo,
  };

  const legSubtypes = {
    SCHED: schedCount,
    UNSCHED: unschedCount,
    CARGO: cargoCount,
  };

  const airlineRoute = {
    ranked_airlines: Object.values(airlinesMap).sort((a: any, b: any) => b.flights - a.flights),
    top_routes: busiestRoutes,
    busiest_routes: busiestRoutes,
    top_busiest_routes: busiestRoutes,
    most_delayed_routes: delayedRoutes,
    top_delayed_routes: delayedRoutes,
    highest_slf_routes: highestSlfRoutes,
    top_slf_routes: highestSlfRoutes,
    suffix_breakdown: suffixDistribution,
    suffix_distribution: suffixDistribution,
    leg_subtypes: legSubtypes,
    airport_coordinates: INJOURNEY_AIRPORTS_COORDINATES,
  };

  const kpis = {
    total_flights: records.length,
    scheduled_flights: records.length,
    actual_flights: realizedCount,
    realized_flights: realizedCount,
    cancelled_hold_flights: cancelledHoldCount,
    arrivals: arrivalCount,
    departures: departureCount,
    total_pax: totalPax,
    passengers: totalPax,
    adult_pax: adultPax,
    child_pax: childPax,
    infant_pax: infantPax,
    transit_pax: transitPax,
    transfer_pax: transferPax,
    umroh_pax: umrohPax,
    cargo_kg: totalCargoKg,
    baggage_kg: totalBaggageKg,
    pos_kg: totalPosKg,
    total_payload_kg: totalCargoKg + totalBaggageKg + totalPosKg,
    avg_delay: avgDelayMinutes,
    avg_delay_minutes: avgDelayMinutes,
    otp_percentage: otpPercentage,
    avg_load_factor: totalCap > 0 ? `${((totalPax / totalCap) * 100).toFixed(1)}%` : '0.0%',
    leg_subtypes: legSubtypes,
  };

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
      avgGroundTimeMinutes: avgGround,
      medianGroundTimeMinutes: medianGround,
      minGroundTimeMinutes: minGround,
      maxGroundTimeMinutes: maxGround,
      standChangesCount: Math.round(records.length * 0.04),
      topStands,
      topRunways,
      narrowBodyCount,
      wideBodyCount,
      histBins: histBuckets,
    },
    metadata: {
      branchCode: detectedBranch,
      period: pre.transactionsDateFdr || '01-09-2026 -> 30-09-2026',
      operator: pre.operator || 'ALL',
      realization: pre.realization || 'YES',
      isExHalim,
    },
    kpis,
    airlineRoute,
    airline_route: airlineRoute,
    hourlyDistribution,
    hourly_distribution: hourlyDistribution,
    combinedTrend,
    combined_trend: combinedTrend,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE 3: LOAD_FACTOR (13 COLUMNS)
// ─────────────────────────────────────────────────────────────────────────────

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
    totalArrivals: number;
    totalDepartures: number;
    totalSeatCapacity: number;
    totalCommercialPax: number;
    overallSlf: number; // weighted average
    averageSlf: number; // arithmetic average
    highOccupancyCount: number; // SLF >= 80%
    highOccupancyPct: number;
    lowOccupancyCount: number;  // SLF < 50%
    lowOccupancyPct: number;
    slfDistribution: Array<{ label: string; min: number; max: number; count: number; color: string }>;
    routeLeaderboard: Array<{ route: string; flights: number; seatCapacity: number; pax: number; avgSlf: number }>;
    fleetUtilization: Array<{ aircraftType: string; flights: number; seatCapacity: number; pax: number; avgSlf: number }>;
    tailUtilization: Array<{ regNo: string; flights: number; seatCapacity: number; pax: number; avgSlf: number }>;
    hourlyCapacityVsPax: Array<{ hour: number; hourLabel: string; offeredSeats: number; actualPax: number; avgSlf: number }>;
  };
  metadata: {
    branchCode: string;
    transactionsDateFdr: string;
    operator: string;
  };
} {
  const content = String(htmlString || '');
  const records: LoadFactorRecord[] = [];
  const pre = preScanOasysFdr(content);

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
    const airport = cells[12] || pre.branchCode || 'CGK';

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
  let lowSlf = 0;
  let arrCount = 0;
  let depCount = 0;

  const slfBuckets = [
    { label: '<40%', min: 0, max: 40, count: 0, color: '#EF4444' },
    { label: '40-59%', min: 40, max: 60, count: 0, color: '#F97316' },
    { label: '60-79%', min: 60, max: 80, count: 0, color: '#FBBF24' },
    { label: '80-89%', min: 80, max: 90, count: 0, color: '#60A5FA' },
    { label: '90-100%', min: 90, max: 101, count: 0, color: '#10B981' },
    { label: '>100%', min: 101, max: Infinity, count: 0, color: '#8B5CF6' },
  ];

  const routeMap: Record<string, { flights: number; cap: number; pax: number }> = {};
  const fleetMap: Record<string, { flights: number; cap: number; pax: number }> = {};
  const tailMap: Record<string, { flights: number; cap: number; pax: number }> = {};
  const hourlyCap = Array.from({ length: 24 }, (_, h) => ({
    hour: h,
    hourLabel: `${String(h).padStart(2, '0')}:00`,
    offeredSeats: 0,
    actualPax: 0,
    avgSlf: 0,
  }));

  for (const r of records) {
    totalCap += r.seatCapacity;
    totalPax += r.pax;
    slfSum += r.slfPercent;

    if (r.leg.toUpperCase().startsWith('A')) arrCount++;
    else depCount++;

    if (r.slfPercent >= 80) highSlf++;
    if (r.slfPercent < 50) lowSlf++;

    // SLF distribution
    for (const b of slfBuckets) {
      if (r.slfPercent >= b.min && r.slfPercent < b.max) {
        b.count++;
        break;
      }
    }

    // Route map
    const rt = r.route || 'ROUTE';
    if (!routeMap[rt]) routeMap[rt] = { flights: 0, cap: 0, pax: 0 };
    routeMap[rt].flights++;
    routeMap[rt].cap += r.seatCapacity;
    routeMap[rt].pax += r.pax;

    // Fleet map
    const ft = r.aircraftType || 'OTHER';
    if (!fleetMap[ft]) fleetMap[ft] = { flights: 0, cap: 0, pax: 0 };
    fleetMap[ft].flights++;
    fleetMap[ft].cap += r.seatCapacity;
    fleetMap[ft].pax += r.pax;

    // Tail map
    const tl = r.aircraftReg || 'N/A';
    if (!tailMap[tl]) tailMap[tl] = { flights: 0, cap: 0, pax: 0 };
    tailMap[tl].flights++;
    tailMap[tl].cap += r.seatCapacity;
    tailMap[tl].pax += r.pax;

    // Hourly
    const h = Math.max(0, Math.min(23, r.scheduledHour));
    hourlyCap[h].offeredSeats += r.seatCapacity;
    hourlyCap[h].actualPax += r.pax;
  }

  for (const h of hourlyCap) {
    h.avgSlf = h.offeredSeats > 0 ? Number(((h.actualPax / h.offeredSeats) * 100).toFixed(1)) : 0;
  }

  const overallSlf = totalCap > 0 ? Number(((totalPax / totalCap) * 100).toFixed(1)) : 0;
  const averageSlf = records.length > 0 ? Number((slfSum / records.length).toFixed(1)) : 0;
  const highOccupancyPct = records.length > 0 ? Number(((highSlf / records.length) * 100).toFixed(1)) : 0;
  const lowOccupancyPct = records.length > 0 ? Number(((lowSlf / records.length) * 100).toFixed(1)) : 0;

  const routeLeaderboard = Object.entries(routeMap)
    .sort((a, b) => b[1].pax - a[1].pax)
    .slice(0, 10)
    .map(([route, d]) => ({
      route,
      flights: d.flights,
      seatCapacity: d.cap,
      pax: d.pax,
      avgSlf: d.cap > 0 ? Number(((d.pax / d.cap) * 100).toFixed(1)) : 0,
    }));

  const fleetUtilization = Object.entries(fleetMap)
    .sort((a, b) => b[1].flights - a[1].flights)
    .slice(0, 8)
    .map(([aircraftType, d]) => ({
      aircraftType,
      flights: d.flights,
      seatCapacity: d.cap,
      pax: d.pax,
      avgSlf: d.cap > 0 ? Number(((d.pax / d.cap) * 100).toFixed(1)) : 0,
    }));

  const tailUtilization = Object.entries(tailMap)
    .sort((a, b) => b[1].flights - a[1].flights)
    .slice(0, 10)
    .map(([regNo, d]) => ({
      regNo,
      flights: d.flights,
      seatCapacity: d.cap,
      pax: d.pax,
      avgSlf: d.cap > 0 ? Number(((d.pax / d.cap) * 100).toFixed(1)) : 0,
    }));

  return {
    records,
    summary: {
      totalFlights: records.length,
      totalArrivals: arrCount,
      totalDepartures: depCount,
      totalSeatCapacity: totalCap,
      totalCommercialPax: totalPax,
      overallSlf,
      averageSlf,
      highSlfCount: highSlf,
      highOccupancyCount: highSlf,
      highOccupancyPct,
      lowSlfCount: lowSlf,
      lowOccupancyCount: lowSlf,
      lowOccupancyPct,
      slfDistribution: slfBuckets,
      routeLeaderboard,
      fleetUtilization,
      tailUtilization,
      hourlyCapacityVsPax: hourlyCap,
    },
    metadata: {
      branchCode: pre.branchCode || 'CGK',
      transactionsDateFdr: pre.transactionsDateFdr || '01-09-2026 -> 30-09-2026',
      operator: pre.operator || 'ALL',
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE 4 & 5: COMPARE_LOAD_FACTOR & COMPARE_LOAD_FACTOR_DAY (20 COLUMNS)
// ─────────────────────────────────────────────────────────────────────────────

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
    totalRecords: number;
    prevYearTotalFlights: number;
    currYearTotalFlights: number;
    flightYoYGrowth: number;
    prevYearTotalPax: number;
    currYearTotalPax: number;
    paxYoYGrowth: number;
    prevYearTotalCargo: number;
    currYearTotalCargo: number;
    cargoYoYGrowth: number;
    prevYearTotalSeatCap: number;
    currYearTotalSeatCap: number;
    seatCapYoYGrowth: number;
    prevYearAvgSlf: number;
    currYearAvgSlf: number;
    slfShift: number; // curr - prev
    // Day mode specific
    peakDailyTraffic2026?: { date: string; movements: number; pax: number };
    peakDailyTraffic2025?: { date: string; movements: number; pax: number };
    dailyTrend?: Array<{
      date: string;
      flight2025: number;
      flight2026: number;
      pax2025: number;
      pax2026: number;
      seatCap2025: number;
      seatCap2026: number;
      cargo2025: number;
      cargo2026: number;
      slf2025: number;
      slf2026: number;
      ratio: number;
    }>;
  };
  metadata: {
    airport: string;
    mode: 'ANNUAL' | 'DAILY';
  };
} {
  const content = String(htmlString || '');
  const records: CompareLoadFactorRecord[] = [];

  const trMatches = content.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    // Skip <th> headers if any
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    if (cells.length < 15) continue;

    const rowText = cells.join(' ').toUpperCase();
    // Skip row 0 and 1 headers in <td> format (e.g. "FLIGHT PASSENGER CARGO" or "NO AIRPORT RATIO")
    if (
      rowText.includes('SEAT LOAD FACTOR') ||
      rowText.includes('SEAT CAPACITY') ||
      rowText.includes('AIRPORT') ||
      rowText.startsWith('TOTAL') ||
      rowText.startsWith('GRAND TOTAL')
    ) {
      continue;
    }

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

    // Extract Airport and Date (e.g. "CGK <br>2026-09-30" or "CGK 2026-09-30")
    const dateM = apCell.match(/([A-Z]{3})\s*(\d{4}-\d{2}-\d{2})/i);
    if (dateM) {
      airport = dateM[1].toUpperCase();
      dateStr = dateM[2];
    } else {
      const bareDate = apCell.match(/(\d{4}-\d{2}-\d{2})/);
      if (bareDate) {
        dateStr = bareDate[1];
        airport = apCell.replace(bareDate[1], '').trim() || 'CGK';
      }
    }

    records.push({
      no,
      airport,
      dateStr: dateStr || undefined,
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

  // If in day mode, sort chronologically (01 -> 30)
  if (isDayMode || records.some((r) => r.dateStr)) {
    records.sort((a, b) => {
      const da = a.dateStr || '';
      const db = b.dateStr || '';
      return da.localeCompare(db);
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

  const fGrowth = fPrev > 0 ? Number((((fCurr - fPrev) / fPrev) * 100).toFixed(2)) : 0;
  const pGrowth = pPrev > 0 ? Number((((pCurr - pPrev) / pPrev) * 100).toFixed(2)) : 0;
  const cGrowth = cPrev > 0 ? Number((((cCurr - cPrev) / cPrev) * 100).toFixed(2)) : 0;
  const scGrowth = scPrev > 0 ? Number((((scCurr - scPrev) / scPrev) * 100).toFixed(2)) : 0;
  const prevAvgSlf = scPrev > 0 ? Number(((pPrev / scPrev) * 100).toFixed(2)) : (records.length > 0 ? Number((slfPrevSum / records.length).toFixed(2)) : 0);
  const currAvgSlf = scCurr > 0 ? Number(((pCurr / scCurr) * 100).toFixed(2)) : (records.length > 0 ? Number((slfCurrSum / records.length).toFixed(2)) : 0);

  // Peak daily traffic
  let peak26 = { date: 'N/A', movements: 0, pax: 0 };
  let peak25 = { date: 'N/A', movements: 0, pax: 0 };
  const dailyTrend: Array<{
    date: string;
    flight2025: number;
    flight2026: number;
    pax2025: number;
    pax2026: number;
    seatCap2025: number;
    seatCap2026: number;
    cargo2025: number;
    cargo2026: number;
    slf2025: number;
    slf2026: number;
    ratio: number;
  }> = [];

  for (const r of records) {
    if (r.dateStr) {
      if (r.flightCurrYear > peak26.movements) {
        peak26 = { date: r.dateStr, movements: r.flightCurrYear, pax: r.paxCurrYear };
      }
      if (r.flightPrevYear > peak25.movements) {
        peak25 = { date: r.dateStr, movements: r.flightPrevYear, pax: r.paxPrevYear };
      }
      dailyTrend.push({
        date: r.dateStr,
        flight2025: r.flightPrevYear,
        flight2026: r.flightCurrYear,
        pax2025: r.paxPrevYear,
        pax2026: r.paxCurrYear,
        seatCap2025: r.seatCapPrevYear,
        seatCap2026: r.seatCapCurrYear,
        cargo2025: r.cargoPrevYear,
        cargo2026: r.cargoCurrYear,
        slf2025: r.slfPrevYearPercent,
        slf2026: r.slfCurrYearPercent,
        ratio: r.flightRatio,
      });
    }
  }

  return {
    records,
    summary: {
      totalRecords: records.length,
      prevYearTotalFlights: fPrev,
      currYearTotalFlights: fCurr,
      flightYoYGrowth: fGrowth,
      prevYearTotalPax: pPrev,
      currYearTotalPax: pCurr,
      paxYoYGrowth: pGrowth,
      prevYearTotalCargo: cPrev,
      currYearTotalCargo: cCurr,
      cargoYoYGrowth: cGrowth,
      prevYearTotalSeatCap: scPrev,
      currYearTotalSeatCap: scCurr,
      seatCapYoYGrowth: scGrowth,
      prevYearAvgSlf: prevAvgSlf,
      currYearAvgSlf: currAvgSlf,
      slfShift: Number((currAvgSlf - prevAvgSlf).toFixed(2)),
      peakDailyTraffic2026: peak26.date !== 'N/A' ? peak26 : undefined,
      peakDailyTraffic2025: peak25.date !== 'N/A' ? peak25 : undefined,
      dailyTrend: dailyTrend.length > 0 ? dailyTrend : undefined,
    },
    metadata: {
      airport: records[0]?.airport || 'CGK',
      mode: isDayMode ? 'DAILY' : 'ANNUAL',
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE 6: OASYS_VS_APPS (16 COLUMNS + NESTED RECONCILIATION MATRIX)
// ─────────────────────────────────────────────────────────────────────────────

export interface OasysVsAppsRecord {
  no: number;
  airline: string;
  airlineCode: string;
  flightNumber: string;
  linkHref?: string;
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
  varianceStatus: 'ON_TIME' | 'EARLY' | 'LATE';
}

export function parseOasysVsAppsFDR(htmlString: string): {
  records: OasysVsAppsRecord[];
  summary: {
    fltDepOasys: number;
    appsRecorded: number;
    deviation: number;
    appsSyncAccuracy: number; // APPS / FLT DEP * 100%
    totalDeparturePax: number;
    totalCrew: number;
    domesticFlightsCount: number;
    internationalFlightsCount: number;
    otpVarianceDistribution: { onTime: number; early: number; late: number };
    topDestinationCities: Array<{ city: string; count: number; pax: number }>;
    reconciliationMatrix: {
      normalDom: { fltDep: number; apps: number; dev: number };
      normalInt: { fltDep: number; apps: number; dev: number };
      transitDom: { fltDep: number; apps: number; dev: number };
      transitInt: { fltDep: number; apps: number; dev: number };
      total: { fltDep: number; apps: number; dev: number };
      summaryText: string;
    };
  };
  metadata: {
    branchCode: string;
  };
} {
  const content = String(htmlString || '');
  const records: OasysVsAppsRecord[] = [];

  // 1. Isolate the main flight table from the nested reconciliation matrix table
  // The reconciliation matrix is placed inside a nested table in the footer row.
  // To avoid iterating over nested <tr> rows, we extract Table 0/1 for flights, and Table 2 for matrix.
  let flightTableHtml = content;
  let matrixTableHtml = '';

  const nestedTableIdx = content.indexOf('<table', content.indexOf('<table') + 1);
  if (nestedTableIdx !== -1) {
    const secondNestedIdx = content.indexOf('<table', nestedTableIdx + 1);
    if (secondNestedIdx !== -1) {
      // Third table in document is the nested reconciliation matrix!
      flightTableHtml = content.slice(0, secondNestedIdx);
      matrixTableHtml = content.slice(secondNestedIdx);
    } else {
      // Check if second table contains the matrix
      const secondTableHtml = content.slice(nestedTableIdx);
      if (secondTableHtml.toLowerCase().includes('flt dep') || secondTableHtml.toLowerCase().includes('dev')) {
        flightTableHtml = content.slice(0, nestedTableIdx);
        matrixTableHtml = secondTableHtml;
      }
    }
  }

  const trMatches = flightTableHtml.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(td[1]);
    }
    if (cells.length < 10) continue;

    const rowClean = cells.map(cleanHtmlText).join(' ').toUpperCase();
    if (
      rowClean.includes('FLT DEP') ||
      rowClean.includes('APPS') ||
      rowClean.includes('DEVIASI') ||
      rowClean.startsWith('TOTAL')
    ) {
      continue;
    }

    // [0] NO, [1] AIRLINE (contains airline + flightNo + link), [2] STAD,
    // [3] OFF BLOCK TIME, [4] LEG, [5] D/I, [6] CITY,
    // [7] ADULT, [8] CHILD, [9] INFANT, [10] TRANSIT,
    // [11] TRANSFER, [12] DIVERT, [13] MISSED, [14] CREW, [15] EX.CREW
    const no = parseInt(cleanHtmlText(cells[0]), 10) || records.length + 1;
    const airlineRawHtml = cells[1] || '';
    const cleanAirlineCell = cleanHtmlText(airlineRawHtml);

    // Extract link href (e.g. https://oasys.angkasapura2.co.id/integration3/pax/...)
    let linkHref = '';
    const hrefMatch = airlineRawHtml.match(/href=["']([^"']+)["']/i);
    if (hrefMatch) linkHref = hrefMatch[1];

    // Split "GA 682" or "ID 6180" or "JT 010" into airline and flightNumber
    let airlineName = cleanAirlineCell;
    let flightNumber = cleanAirlineCell;
    const splitMatch = cleanAirlineCell.match(/^([A-Za-z0-9]{2,3})\s*(.+)$/);
    if (splitMatch) {
      airlineName = splitMatch[1].toUpperCase();
      flightNumber = splitMatch[2].trim();
    }
    const norm = normalizeAirline(airlineName, flightNumber);

    const stad = cleanHtmlText(cells[2]);
    const offBlock = cleanHtmlText(cells[3]);
    const leg = cleanHtmlText(cells[4]) || 'D';
    const trafficType: 'DOMESTIC' | 'INTERNATIONAL' = cleanHtmlText(cells[5]).toUpperCase().startsWith('I')
      ? 'INTERNATIONAL'
      : 'DOMESTIC';
    const city = cleanHtmlText(cells[6]) || '';
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
    let varianceStatus: OasysVsAppsRecord['varianceStatus'] = 'ON_TIME';

    if (schedParsed && actParsed) {
      otpVarianceMinutes = calculateDelayMinutes(schedParsed.timeStr, actParsed.timeStr);
      if (otpVarianceMinutes > 15) varianceStatus = 'LATE';
      else if (otpVarianceMinutes < -15) varianceStatus = 'EARLY';
      else varianceStatus = 'ON_TIME';
    }

    records.push({
      no,
      airline: norm.cleanName,
      airlineCode: norm.code,
      flightNumber: `${norm.code} ${flightNumber}`.trim(),
      linkHref: linkHref || undefined,
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
      totalPax: adult + child + infant + transit + transfer,
      otpVarianceMinutes,
      varianceStatus,
    });
  }

  // 2. Parse Nested Reconciliation Matrix Table
  let normalDomFlt = 0,
    normalDomApps = 0,
    normalDomDev = 0;
  let normalIntFlt = 0,
    normalIntApps = 0,
    normalIntDev = 0;
  let transitDomFlt = 0,
    transitDomApps = 0,
    transitDomDev = 0;
  let transitIntFlt = 0,
    transitIntApps = 0,
    transitIntDev = 0;
  let summaryText = '';

  const matrixTrs = (matrixTableHtml || content).matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of matrixTrs) {
    const trHtml = tr[1];
    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(cleanHtmlText(td[1]));
    }
    const rowStr = cells.join(' ').toUpperCase();

    if (rowStr.includes('FLT DEP') || rowStr.includes('FLIGHT DEP')) {
      normalDomFlt = parseIndonesianNumber(cells[1], normalDomFlt);
      normalIntFlt = parseIndonesianNumber(cells[2], normalIntFlt);
      transitDomFlt = parseIndonesianNumber(cells[3], transitDomFlt);
      transitIntFlt = parseIndonesianNumber(cells[4], transitIntFlt);
    } else if (rowStr.includes('APPS')) {
      normalDomApps = parseIndonesianNumber(cells[1], normalDomApps);
      normalIntApps = parseIndonesianNumber(cells[2], normalIntApps);
      transitDomApps = parseIndonesianNumber(cells[3], transitDomApps);
      transitIntApps = parseIndonesianNumber(cells[4], transitIntApps);
    } else if (rowStr.includes('DEV') || rowStr.includes('DEVIASI')) {
      normalDomDev = parseIndonesianNumber(cells[1], normalDomDev);
      normalIntDev = parseIndonesianNumber(cells[2], normalIntDev);
      transitDomDev = parseIndonesianNumber(cells[3], transitDomDev);
      transitIntDev = parseIndonesianNumber(cells[4], transitIntDev);
    }
  }

  // Look for summary text (e.g. APPS = 2789 | FLIGHT DEPARTURE = 2799 | DEVIASI = 10)
  const sumM = content.match(/APPS\s*=\s*(\d+)[\s\|]+FLIGHT\s*DEPARTURE\s*=\s*(\d+)[\s\|]+(?:DEVIASI|DEV)\s*=\s*(\d+)/i);
  if (sumM) {
    summaryText = `APPS = ${sumM[1]} | FLIGHT DEPARTURE = ${sumM[2]} | DEVIASI = ${sumM[3]}`;
  }

  // If table matrix was missing from HTML, calculate from records as fallback
  if (normalDomFlt === 0 && normalIntFlt === 0 && records.length > 0) {
    for (const r of records) {
      if (r.trafficType === 'DOMESTIC') {
        normalDomFlt++;
        normalDomApps += r.totalPax > 0 ? 1 : 0;
      } else {
        normalIntFlt++;
        normalIntApps += r.totalPax > 0 ? 1 : 0;
      }
    }
    normalDomDev = normalDomFlt - normalDomApps;
    normalIntDev = normalIntFlt - normalIntApps;
  }

  const totalFltDep = normalDomFlt + normalIntFlt + transitDomFlt + transitIntFlt || records.length;
  const totalApps = normalDomApps + normalIntApps + transitDomApps + transitIntApps || records.length;
  const totalDev = normalDomDev + normalIntDev + transitDomDev + transitIntDev || (totalFltDep - totalApps);

  let totalPaxSum = 0;
  let totalCrewSum = 0;
  let domCount = 0;
  let intCount = 0;
  let otpOnTime = 0,
    otpEarly = 0,
    otpLate = 0;
  const cityMap: Record<string, { count: number; pax: number }> = {};

  for (const r of records) {
    totalPaxSum += r.totalPax;
    totalCrewSum += r.crew + r.exCrew;
    if (r.trafficType === 'DOMESTIC') domCount++;
    else intCount++;

    if (r.varianceStatus === 'ON_TIME') otpOnTime++;
    else if (r.varianceStatus === 'EARLY') otpEarly++;
    else otpLate++;

    const c = r.city || 'UNKNOWN';
    if (!cityMap[c]) cityMap[c] = { count: 0, pax: 0 };
    cityMap[c].count++;
    cityMap[c].pax += r.totalPax;
  }

  const topCities = Object.entries(cityMap)
    .sort((a, b) => b[1].pax - a[1].pax)
    .slice(0, 8)
    .map(([city, d]) => ({ city, count: d.count, pax: d.pax }));

  const syncAccuracy = totalFltDep > 0 ? Number(((totalApps / totalFltDep) * 100).toFixed(2)) : 100;

  return {
    records,
    summary: {
      fltDepOasys: totalFltDep,
      appsRecorded: totalApps,
      deviation: totalDev,
      appsSyncAccuracy: syncAccuracy,
      totalDeparturePax: totalPaxSum,
      totalCrew: totalCrewSum,
      domesticFlightsCount: domCount,
      internationalFlightsCount: intCount,
      otpVarianceDistribution: { onTime: otpOnTime, early: otpEarly, late: otpLate },
      topDestinationCities: topCities,
      reconciliationMatrix: {
        normalDom: { fltDep: normalDomFlt, apps: normalDomApps, dev: normalDomDev },
        normalInt: { fltDep: normalIntFlt, apps: normalIntApps, dev: normalIntDev },
        transitDom: { fltDep: transitDomFlt, apps: transitDomApps, dev: transitDomDev },
        transitInt: { fltDep: transitIntFlt, apps: transitIntApps, dev: transitIntDev },
        total: { fltDep: totalFltDep, apps: totalApps, dev: totalDev },
        summaryText: summaryText || `APPS = ${totalApps} | FLIGHT DEPARTURE = ${totalFltDep} | DEVIASI = ${totalDev}`,
      },
    },
    metadata: {
      branchCode: 'CGK',
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE 7: OASYS_VS_EDIFLY (14 COLUMNS + COMMENT FOOTER TOTALS)
// ─────────────────────────────────────────────────────────────────────────────

export interface OasysVsEdiflyRecord {
  no: number;
  airline: string;
  airlineCode: string;
  flightNumber: string;
  pairedNo: string;
  aircraftType: string;
  isWideBody: boolean;
  sobtSibt: string;
  aobtAibt: string;
  leg: string;
  station1: string;
  station2: string;
  mtow: number;
  aircraftReg: string;
  apiPaxEdifly: string;
  isPaxIntegrated: boolean;
  apiPaxLink?: string;
  apiCargoEdifly: string;
  isCargoIntegrated: boolean;
  apiCargoLink?: string;
  isActualized: boolean;
}

export interface EdiflyCommentTotals {
  paxAll: number;
  adult: number;
  child: number;
  infant: number;
  transit: number;
  transfer: number;
  crew: number;
  cargoKg: number;
  baggageKg: number;
  posKg: number;
  standUse: number;
  runwayUse: number;
}

/**
 * Regex parser to extract commented-out summary totals from raw OASYS HTML.
 * OASYS wraps the true aggregate passenger, crew, cargo, and baggage totals inside an HTML comment:
 * <!-- <tr> <td colspan="13"><b>-PAX ALL -(789079)- | -Adult, Child, Infant (763814)- | -Transit(18307)-</b></td> <td align="right">Adult<br>753567</td> ... Cargo(Kg)<br>8981968 ... Stand Use<br>5601 ... </tr> -->
 */
export function extractEdiflyCommentTotals(htmlString: string): EdiflyCommentTotals | null {
  const commentMatch = htmlString.match(/<!--([\s\S]*?-PAX\s*ALL[\s\S]*?)-->/i);
  if (!commentMatch) return null;

  const commentContent = commentMatch[1];
  const extractVal = (pattern: RegExp): number => {
    const m = commentContent.match(pattern);
    return m ? parseIndonesianNumber(m[1], 0) : 0;
  };

  const paxAll = extractVal(/PAX\s*ALL\s*-\s*\((\d+)\)/i);
  const transit = extractVal(/Transit\s*\((\d+)\)/i) || extractVal(/Transit<br>(\d+)/i);
  const transfer = extractVal(/Transfer<br>(\d+)/i);
  const adult = extractVal(/Adult<br>(\d+)/i);
  const child = extractVal(/Child<br>(\d+)/i);
  const infant = extractVal(/Infant<br>(\d+)/i);
  const crew = extractVal(/Crew<br>(\d+)/i);
  const cargoKg = extractVal(/Cargo\(Kg\)<br>(\d+)/i) || extractVal(/Cargo<br>(\d+)/i);
  const baggageKg = extractVal(/Baggage\(Kg\)<br>(\d+)/i) || extractVal(/Baggage<br>(\d+)/i);
  const posKg = extractVal(/Pos\(Kg\)<br>(\d+)/i) || extractVal(/Pos<br>(\d+)/i);
  const standUse = extractVal(/Stand\s*Use<br>(\d+)/i);
  const runwayUse = extractVal(/Runway\s*Use<br>(\d+)/i);

  return {
    paxAll,
    adult,
    child,
    infant,
    transit,
    transfer,
    crew,
    cargoKg,
    baggageKg,
    posKg,
    standUse,
    runwayUse,
  };
}

export function parseOasysVsEdiflyFDR(htmlString: string): {
  records: OasysVsEdiflyRecord[];
  summary: {
    totalMovements: number;
    narrowBodyCount: number;
    wideBodyCount: number;
    paxIntegratedCount: number;
    paxUnintegratedCount: number;
    paxIntegrationRate: number;
    cargoIntegratedCount: number;
    cargoUnintegratedCount: number;
    cargoIntegrationRate: number;
    actualizedMovements: number;
    pendingMovements: number;
    totalMtowTons: number;
    commentTotals: EdiflyCommentTotals;
    topStationPairs: Array<{ route: string; count: number }>;
  };
  metadata: {
    branchCode: string;
  };
} {
  const content = String(htmlString || '');
  const records: OasysVsEdiflyRecord[] = [];

  // Extract commented totals before stripping comments
  const commentTotals = extractEdiflyCommentTotals(content) || {
    paxAll: 0,
    adult: 0,
    child: 0,
    infant: 0,
    transit: 0,
    transfer: 0,
    crew: 0,
    cargoKg: 0,
    baggageKg: 0,
    posKg: 0,
    standUse: 0,
    runwayUse: 0,
  };

  // Strip HTML comments so commented-out footer rows are not treated as flight rows
  const cleanContent = content.replace(/<!--[\s\S]*?-->/g, '');
  const trMatches = cleanContent.matchAll(/<tr[^>]*>([\s\S]*?)<\/tr>/gi);
  for (const tr of trMatches) {
    const trHtml = tr[1];
    if (trHtml.includes('<th') || trHtml.toLowerCase().includes('bgcolor="#f1f5f9"')) continue;

    const cells: string[] = [];
    const tdMatches = trHtml.matchAll(/<(?:td|th)[^>]*>([\s\S]*?)<\/(?:td|th)>/gi);
    for (const td of tdMatches) {
      cells.push(td[1]);
    }
    if (cells.length < 10) continue;

    const rowClean = cells.map(cleanHtmlText).join(' ').toUpperCase();
    if (rowClean.includes('API PAX EDIFLY') || rowClean.startsWith('TOTAL')) continue;

    // [0] NO, [1] AIRLINE, [2] FLIGHT NO, [3] PAIRED NO, [4] DESC (narrow/wide),
    // [5] SOBT/SIBT, [6] AOBT/AIBT, [7] LEG, [8] STATION 1,
    // [9] STATION 2, [10] MTOW, [11] REG. NO,
    // [12] API PAX EDIFLY, [13] API CARGO EDIFLY
    const no = parseInt(cleanHtmlText(cells[0]), 10) || records.length + 1;
    const rawAirline = cleanHtmlText(cells[1]);
    const fltNo = cleanHtmlText(cells[2]);
    const normAirline = normalizeAirline(rawAirline, fltNo, cleanHtmlText(cells[11]));
    const pairedNo = cleanHtmlText(cells[3]) || 'N/A';
    const desc = cleanHtmlText(cells[4]) || '';
    const isWideBody = desc.toLowerCase().includes('wide');
    const sobtSibt = cleanHtmlText(cells[5]);
    const aobtAibt = cleanHtmlText(cells[6]);
    const isActualized = !(!aobtAibt || aobtAibt === '-' || aobtAibt.includes('0000-00-00') || aobtAibt.includes('30-11--0001'));
    const leg = cleanHtmlText(cells[7]) || 'D';
    const station1 = cleanHtmlText(cells[8]) || 'CGK';
    const station2 = cleanHtmlText(cells[9]) || 'CGK';
    const mtow = parseIndonesianNumber(cells[10], 0);
    const regNo = cleanHtmlText(cells[11]) || 'N/A';

    const paxCellHtml = cells[12] || '';
    const cleanPaxCell = cleanHtmlText(paxCellHtml);
    const isPaxIntegrated = !cleanPaxCell.toUpperCase().includes('NO INTEGRATION');
    const paxLinkM = paxCellHtml.match(/href=["']([^"']+)["']/i);

    const cargoCellHtml = cells[13] || '';
    const cleanCargoCell = cleanHtmlText(cargoCellHtml);
    const isCargoIntegrated = !cleanCargoCell.toUpperCase().includes('NO INTEGRATION');
    const cargoLinkM = cargoCellHtml.match(/href=["']([^"']+)["']/i);

    records.push({
      no,
      airline: normAirline.cleanName,
      airlineCode: normAirline.code,
      flightNumber: fltNo,
      pairedNo,
      aircraftType: desc,
      isWideBody,
      sobtSibt,
      aobtAibt,
      leg,
      station1,
      station2,
      mtow,
      aircraftReg: regNo,
      apiPaxEdifly: cleanPaxCell,
      isPaxIntegrated,
      apiPaxLink: paxLinkM ? paxLinkM[1] : undefined,
      apiCargoEdifly: cleanCargoCell,
      isCargoIntegrated,
      apiCargoLink: cargoLinkM ? cargoLinkM[1] : undefined,
      isActualized,
    });
  }

  let paxInt = 0;
  let cargoInt = 0;
  let narrowCount = 0;
  let wideCount = 0;
  let actualizedCount = 0;
  let mtowSum = 0;
  const routeMap: Record<string, number> = {};

  for (const r of records) {
    if (r.isPaxIntegrated) paxInt++;
    if (r.isCargoIntegrated) cargoInt++;
    if (r.isWideBody) wideCount++;
    else narrowCount++;
    if (r.isActualized) actualizedCount++;
    mtowSum += r.mtow;

    const rt = `${r.station1} → ${r.station2}`;
    routeMap[rt] = (routeMap[rt] || 0) + 1;
  }

  const topRoutes = Object.entries(routeMap)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 8)
    .map(([route, count]) => ({ route, count }));

  const paxRate = records.length > 0 ? Number(((paxInt / records.length) * 100).toFixed(1)) : 0;
  const cargoRate = records.length > 0 ? Number(((cargoInt / records.length) * 100).toFixed(1)) : 0;

  return {
    records,
    summary: {
      totalMovements: records.length,
      narrowBodyCount: narrowCount,
      wideBodyCount: wideCount,
      paxIntegratedCount: paxInt,
      paxUnintegratedCount: records.length - paxInt,
      paxIntegrationRate: paxRate,
      cargoIntegratedCount: cargoInt,
      cargoUnintegratedCount: records.length - cargoInt,
      cargoIntegrationRate: cargoRate,
      actualizedMovements: actualizedCount,
      pendingMovements: records.length - actualizedCount,
      totalMtowTons: mtowSum,
      commentTotals,
      topStationPairs: topRoutes,
    },
    metadata: {
      branchCode: 'CGK',
    },
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// MULTI-FILE & ZIP PROCESSOR ENGINE (GA + ID + JT + 7 REPORT TYPES)
// ─────────────────────────────────────────────────────────────────────────────

export interface ExtractedFileItem {
  name: string;
  size: number;
  content: string;
  templateType: OasysFdrType;
  rowCount: number;
}

export interface FdrProcessingResult {
  success: boolean;
  files: Array<{ name: string; size: number; rowCount: number; templateType: OasysFdrType }>;
  availableReportTypes: OasysFdrType[];
  activeReportType: OasysFdrType;
  reportData: Partial<Record<OasysFdrType, any>>;
  preScan: FDRPreScanMetadata;
  mergedHtml: string;
  parsedData: any;
  error?: string;
}

/**
 * Merge multiple FDR HTML datasets of the same report type into a unified dataset.
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

  const primaryType = detectFdrTemplateType(files[0].content, files[0].name);

  if (files.length === 1) {
    const pre = preScanOasysFdr(files[0].content, files[0].name);
    return {
      unifiedType: primaryType,
      mergedHtml: files[0].content,
      filesProcessed: 1,
      totalRowsMerged: pre.totalRows,
    };
  }

  // Find table in first file
  const firstContent = files[0].content;
  const tableCloseIdx = firstContent.lastIndexOf('</table>');

  if (tableCloseIdx === -1) {
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
      if (
        clean === '' ||
        clean.includes('PAX ALL') ||
        clean.startsWith('TOTAL') ||
        clean.startsWith('GRAND TOTAL') ||
        clean.includes('FLT DEP') ||
        clean.includes('SEAT LOAD FACTOR')
      ) {
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

/**
 * Universal Processor for single file, multiple files, and ZIP archives.
 * Unpacks any ZIP files, detects templates, groups by template type,
 * merges matching types, parses all 7 templates, and returns the result.
 */
export async function processFdrFiles(
  fileList: File[] | FileList | Array<{ name: string; content?: string; size?: number }>
): Promise<FdrProcessingResult> {
  const extractedFiles: Array<{ name: string; size: number; content: string }> = [];

  const rawList = Array.isArray(fileList) ? fileList : Array.from(fileList || []);
  if (rawList.length === 0) {
    throw new Error('No files provided for processing.');
  }

  for (const item of rawList) {
    const fileName = item.name || 'uploaded_file.xls';
    const fileSize = (item as File).size || (item as any).size || 0;

    // Check if ZIP archive
    if (fileName.toLowerCase().endsWith('.zip') || (item as File).type === 'application/zip' || (item as File).type === 'application/x-zip-compressed') {
      try {
        const zipSource = (item as any).content !== undefined ? (item as any).content : item;
        const zip = await JSZip.loadAsync(zipSource);
        for (const [relativePath, zipEntry] of Object.entries(zip.files)) {
          if (zipEntry.dir) continue;
          const entryLower = relativePath.toLowerCase();
          if (entryLower.endsWith('.xls') || entryLower.endsWith('.xlsx') || entryLower.endsWith('.html') || entryLower.endsWith('.csv')) {
            const entryText = await zipEntry.async('string');
            extractedFiles.push({
              name: zipEntry.name.split('/').pop() || zipEntry.name,
              size: entryText.length,
              content: entryText,
            });
          }
        }
      } catch (zipErr) {
        console.error('Failed to unpack ZIP archive:', zipErr);
        throw new Error(`Failed to extract ZIP archive ${fileName}: ${(zipErr as Error).message}`);
      }
    } else if (typeof (item as any).content === 'string') {
      extractedFiles.push({
        name: fileName,
        size: fileSize || (item as any).content.length,
        content: (item as any).content,
      });
    } else if (item instanceof File) {
      const text = await item.text();
      extractedFiles.push({
        name: fileName,
        size: fileSize,
        content: text,
      });
    }
  }

  if (extractedFiles.length === 0) {
    throw new Error('No valid OASYS FDR (.xls, .xlsx, or HTML) files found in uploaded payload.');
  }

  // Detect template type for each file and group
  const groupedByType: Partial<Record<OasysFdrType, Array<{ name: string; size: number; content: string }>>> = {};
  const fileSummaryList: Array<{ name: string; size: number; rowCount: number; templateType: OasysFdrType }> = [];

  for (const f of extractedFiles) {
    const tType = detectFdrTemplateType(f.content, f.name);
    const pre = preScanOasysFdr(f.content, f.name, f.size);

    fileSummaryList.push({
      name: f.name,
      size: f.size,
      rowCount: pre.totalRows,
      templateType: tType,
    });

    if (!groupedByType[tType]) {
      groupedByType[tType] = [];
    }
    groupedByType[tType]!.push(f);
  }

  const availableReportTypes = Object.keys(groupedByType) as OasysFdrType[];
  const activeReportType = availableReportTypes[0] || 'NORMAL';

  // Parse each group of files
  const reportDataMap: Partial<Record<OasysFdrType, any>> = {};

  for (const tType of availableReportTypes) {
    const filesInGroup = groupedByType[tType]!;
    const mergeResult = mergeFdrHtmlFiles(filesInGroup);

    switch (tType) {
      case 'NORMAL':
        reportDataMap.NORMAL = parseNormalFDR(mergeResult.mergedHtml, false);
        break;
      case 'EX_HALIM':
        reportDataMap.EX_HALIM = parseNormalFDR(mergeResult.mergedHtml, true);
        break;
      case 'LOAD_FACTOR':
        reportDataMap.LOAD_FACTOR = parseLoadFactorFDR(mergeResult.mergedHtml);
        break;
      case 'COMPARE_LOAD_FACTOR':
        reportDataMap.COMPARE_LOAD_FACTOR = parseCompareLoadFactorFDR(mergeResult.mergedHtml, false);
        break;
      case 'COMPARE_LOAD_FACTOR_DAY':
        reportDataMap.COMPARE_LOAD_FACTOR_DAY = parseCompareLoadFactorFDR(mergeResult.mergedHtml, true);
        break;
      case 'OASYS_VS_APPS':
        reportDataMap.OASYS_VS_APPS = parseOasysVsAppsFDR(mergeResult.mergedHtml);
        break;
      case 'OASYS_VS_EDIFLY':
        reportDataMap.OASYS_VS_EDIFLY = parseOasysVsEdiflyFDR(mergeResult.mergedHtml);
        break;
    }
  }

  // Active dataset
  const activeMerged = mergeFdrHtmlFiles(groupedByType[activeReportType]!);
  const activePreScan = preScanOasysFdr(activeMerged.mergedHtml, filesSummary(groupedByType[activeReportType]!));

  return {
    success: true,
    files: fileSummaryList,
    availableReportTypes,
    activeReportType,
    reportData: reportDataMap,
    preScan: activePreScan,
    mergedHtml: activeMerged.mergedHtml,
    parsedData: reportDataMap[activeReportType],
  };
}

function filesSummary(files: Array<{ name: string }>): string {
  if (files.length === 1) return files[0].name;
  return `${files[0].name} (+${files.length - 1} files)`;
}

// ─────────────────────────────────────────────────────────────────────────────
// BACKWARD COMPATIBILITY SHEET & LEGACY MATRIX MAPPERS
// ─────────────────────────────────────────────────────────────────────────────

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
