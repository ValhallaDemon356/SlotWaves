/**
 * SlotWaves: Flight Daily Report (FDR) OASYS Normalizer & Parser
 *
 * Handles OASYS Excel formats (.xls / .xlsx / HTML-XLS) from airlines like:
 * Garuda Indonesia (GA), Batik Air (ID), Super Air Jet (IU), TransNusa (8B),
 * Lion Air (JT), Citilink (QG), Pelita Air (IP).
 */

export interface NormalizedFDRRecord {
  flightDate: string;
  airline: string;
  flightNumber: string;
  aircraftReg: string;
  aircraftType: string;
  movementType: 'Arrival' | 'Departure';
  origin: string;
  destination: string;
  scheduledTime: string;
  actualTime: string;
  scheduledHour: number;
  actualHour: number;
  delayMinutes: number;
  status: 'ON_TIME' | 'DELAYED' | 'EARLY' | 'CANCELLED';
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

/**
 * Convert Excel serial decimal time (e.g. 0.354166667) or string time ("08:30", "08:30:00")
 * to standard "HH:mm" and extract integer hour (0–23).
 */
export function parseExcelTime(val: unknown): { timeStr: string; hour: number } | null {
  if (val === null || val === undefined) return null;
  const str = String(val).trim();
  if (str === '' || str === '-' || str === 'N/A' || str === 'NULL' || str === '&nbsp;') return null;

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
  const isDecimal = typeof val === 'number' || (!isNaN(Number(str)) && str.includes('.') && !str.includes(':') && !str.includes('-') && !str.includes('/'));
  if (isDecimal) {
    const num = typeof val === 'number' ? val : parseFloat(str);
    if (!isNaN(num) && num >= 0) {
      // Excel fractional day (e.g. 0.354166667 * 24 = 8.5 -> 08:30)
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
  const str = String(val).trim();
  if (str === '' || str === '-' || str === 'N/A') return fallbackDate || new Date().toISOString().slice(0, 10);

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
 * Dynamically find table header row and map columns based on keywords:
 * FLT, DATE, REG, STD/STA, DEP/ARR.
 * Scans rows 0 through 15 (typically rows 4 to 7 in OASYS workbooks).
 */
export function findFDRHeaderRow(rows: unknown[][]): FDRHeaderMapping | null {
  const maxScanRows = Math.min(20, rows.length);

  for (let r = 0; r < maxScanRows; r++) {
    const row = rows[r];
    if (!row || !Array.isArray(row)) continue;

    let matchedKeywords = 0;
    const cols: FDRHeaderMapping['columns'] = {};

    for (let c = 0; c < row.length; c++) {
      const cellText = String(row[c] || '').replace(/<[^>]+>/g, '').trim().toUpperCase();
      if (!cellText) continue;

      // Flight Number
      if (!cols.flightNumber && (cellText.includes('FLT') || cellText.includes('FLIGHT NO') || cellText === 'FLIGHT' || cellText === 'NO PENERBANGAN')) {
        cols.flightNumber = c;
        matchedKeywords++;
        continue;
      }

      // Date
      if (!cols.flightDate && (cellText === 'DATE' || cellText.includes('FLIGHT DATE') || cellText === 'TGL' || cellText.includes('TANGGAL') || cellText.includes('OP DATE'))) {
        cols.flightDate = c;
        matchedKeywords++;
        continue;
      }

      // Aircraft Registration
      if (!cols.aircraftReg && (cellText.includes('REG') || cellText.includes('TAIL') || cellText === 'NO REG' || cellText === 'NO REGISTRASI')) {
        cols.aircraftReg = c;
        matchedKeywords++;
        continue;
      }

      // Scheduled Time: STD or STA or SIBT or SOBT
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

      // Actual Time: ATD or ATA or AIBT or AOBT
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

      // Movement Type / Leg / Direction
      if (!cols.movementType && (cellText === 'LEG' || cellText.includes('DEP/ARR') || cellText.includes('A/D') || cellText === 'DIRECTION' || cellText === 'STATUS LEG')) {
        cols.movementType = c;
        matchedKeywords++;
        continue;
      }

      // Origin Airport
      if (!cols.origin && (cellText === 'ORIGIN' || cellText === 'CITY 1' || cellText === 'CITY1' || cellText === 'DEP APT' || cellText === 'ASAL' || cellText === 'FROM')) {
        cols.origin = c;
        continue;
      }

      // Destination Airport
      if (!cols.destination && (cellText === 'DESTINATION' || cellText === 'DEST' || cellText === 'CITY 2' || cellText === 'CITY2' || cellText === 'ARR APT' || cellText === 'TUJUAN' || cellText === 'TO')) {
        cols.destination = c;
        continue;
      }

      // Airline / Operator
      if (!cols.airline && (cellText.includes('AIRLINE') || cellText.includes('AIR LINE') || cellText === 'OPERATOR' || cellText === 'MASKAPAI')) {
        cols.airline = c;
        continue;
      }

      // Aircraft Type
      if (!cols.aircraftType && (cellText.includes('AC TYPE') || cellText.includes('AIRCRAFT') || cellText === 'TYPE' || cellText === 'DESC' || cellText === 'KETERANGAN')) {
        cols.aircraftType = c;
        continue;
      }
    }

    // Must match at least 3 key indicators (e.g. FLT, STD/STA, REG, or DATE)
    if (matchedKeywords >= 3 || ((cols.flightNumber !== undefined || cols.airline !== undefined) && (cols.sibt !== undefined || cols.sobt !== undefined || cols.sibtSobt !== undefined || cols.scheduledTime !== undefined))) {
      return {
        headerRowIndex: r,
        columns: cols,
      };
    }
  }

  return null;
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
 * Determine flight movement direction (Arrival vs Departure) relative to reference airport (default CGK).
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

/**
 * Ingest, parse, and normalize a complete FDR spreadsheet table into NormalizedFDRRecord[].
 */
export function parseFDRSheet(rows: unknown[][], baseAirport = 'CGK', fallbackDate?: string): NormalizedFDRRecord[] {
  if (!rows || rows.length === 0) return [];

  const mapping = findFDRHeaderRow(rows);
  if (!mapping) {
    return [];
  }

  const { headerRowIndex, columns } = mapping;
  const records: NormalizedFDRRecord[] = [];

  for (let r = headerRowIndex + 1; r < rows.length; r++) {
    const row = rows[r];
    if (!row || !Array.isArray(row) || row.length === 0) continue;

    const getVal = (idx?: number): string => {
      if (idx === undefined || row[idx] === undefined || row[idx] === null) return '';
      return String(row[idx]).replace(/<[^>]+>/g, '').trim();
    };

    const rawAirline = getVal(columns.airline);
    const rawFlt = getVal(columns.flightNumber);

    // Skip summary / grand total rows
    if (
      rawAirline.toUpperCase().includes('PAX ALL') ||
      rawFlt.toUpperCase().includes('TOTAL') ||
      rawFlt.toUpperCase().includes('GRAND')
    ) {
      continue;
    }

    if (!rawAirline && !rawFlt) continue;

    // Movement direction
    const rawOrig = getVal(columns.origin);
    const rawDest = getVal(columns.destination);
    const rawLeg = getVal(columns.movementType);
    const rawSibt = getVal(columns.sibt);
    const rawSobt = getVal(columns.sobt);
    const rawAibt = getVal(columns.aibt);
    const rawAobt = getVal(columns.aobt);

    const movementType = resolveMovementType(rawOrig, rawDest, rawLeg, rawSibt, rawSobt, baseAirport);

    // Time extraction: Arrival uses SIBT/STA & AIBT/ATA; Departure uses SOBT/STD & AOBT/ATD
    let rawSchedTime = '';
    let rawActTime = '';

    if (movementType === 'Arrival') {
      rawSchedTime = rawSibt || getVal(columns.scheduledTime);
      rawActTime = rawAibt || getVal(columns.actualTime);
    } else {
      rawSchedTime = rawSobt || getVal(columns.scheduledTime);
      rawActTime = rawAobt || getVal(columns.actualTime);
    }

    // Split combined "SIBT SOBT" or "AIBT AOBT" if present
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

    // Flight Date
    const rawDateVal = columns.flightDate !== undefined ? row[columns.flightDate] : (rawSchedTime || fallbackDate);
    const flightDate = parseExcelDate(rawDateVal, fallbackDate);

    // Delay & Status
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

    // Normalized Airline clean name
    let airline = rawAirline || 'N/A';
    if (/\b(GA|GARUDA)\b/i.test(rawAirline) || /\bGA\s*\d+/i.test(rawFlt)) airline = 'Garuda Indonesia';
    else if (/\b(ID|BATIK)\b/i.test(rawAirline) || /\bID\s*\d+/i.test(rawFlt)) airline = 'Batik Air';
    else if (/\b(IU|SUPER\s*AIR\s*JET)\b/i.test(rawAirline) || /\bIU\s*\d+/i.test(rawFlt)) airline = 'Super Air Jet';
    else if (/\b(8B|TRANSNUSA)\b/i.test(rawAirline) || /\b8B\s*\d+/i.test(rawFlt)) airline = 'TransNusa';
    else if (/\b(JT|LION)\b/i.test(rawAirline) || /\bJT\s*\d+/i.test(rawFlt)) airline = 'Lion Air';
    else if (/\b(QG|CITILINK)\b/i.test(rawAirline) || /\bQG\s*\d+/i.test(rawFlt)) airline = 'Citilink';
    else if (/\b(IP|PELITA)\b/i.test(rawAirline) || /\bIP\s*\d+/i.test(rawFlt)) airline = 'Pelita Air';

    records.push({
      flightDate,
      airline,
      flightNumber: rawFlt || 'N/A',
      aircraftReg: getVal(columns.aircraftReg) || 'N/A',
      aircraftType: getVal(columns.aircraftType) || 'N/A',
      movementType,
      origin: rawOrig || (movementType === 'Departure' ? baseAirport : 'N/A'),
      destination: rawDest || (movementType === 'Arrival' ? baseAirport : 'N/A'),
      scheduledTime,
      actualTime,
      scheduledHour,
      actualHour,
      delayMinutes,
      status,
    });
  }

  return records;
}

/**
 * Aggregate normalized FDR records into strictly 24 hourly buckets (00:00 to 23:00)
 * for Slot Waves visualizations.
 */
export function aggregateHourlySlotWaves(records: NormalizedFDRRecord[], timeBasis: 'actual' | 'scheduled' = 'actual'): HourlySlotWavesSummary {
  const buckets: HourlySlotWavesBucket[] = [];

  for (let h = 0; h < 24; h++) {
    const hStr = String(h).padStart(2, '0');
    const nextHStr = String(h).padStart(2, '0');
    buckets.push({
      hour: h,
      hourLabel: `${hStr}:00`,
      timeRange: `${hStr}:00–${nextHStr}:59`,
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
    } else if (r.status === 'CANCELLED') {
      b.cancelled++;
      totalCancelled++;
    }

    const al = r.airline || 'Unknown';
    b.airlines[al] = (b.airlines[al] || 0) + 1;
  }

  // Peak hour
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
