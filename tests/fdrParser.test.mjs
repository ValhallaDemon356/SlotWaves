import assert from 'node:assert';
import {
  parseExcelTime,
  parseExcelDate,
  calculateDelayMinutes,
  resolveMovementType,
  findFDRHeaderRow,
  parseFDRSheet,
  aggregateHourlySlotWaves,
  detectOasysFdrType,
  preScanOasysFdr,
  extractFlightSuffix,
  normalizeAirline,
  parseIndonesianNumber,
  parseNormalFDR,
  parseLoadFactorFDR,
  parseCompareLoadFactorFDR,
  parseOasysVsAppsFDR,
  parseOasysVsEdiflyFDR,
  mergeFdrHtmlFiles,
  INJOURNEY_AIRPORTS_COORDINATES,
  OASYS_SUFFIX_DICTIONARY
} from '../src/utils/fdrParser.ts';

console.log('--- 1. Testing Legacy Backward Compatibility ---');
assert.deepStrictEqual(parseExcelTime(0.354166667), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("0.354166667"), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("08:30"), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("14:45:00"), { timeStr: '14:45', hour: 14 });
assert.deepStrictEqual(parseExcelTime("0615"), { timeStr: '06:15', hour: 6 });
assert.strictEqual(parseExcelTime("-"), null);
assert.strictEqual(parseExcelTime("N/A"), null);
assert.strictEqual(parseExcelTime("30-11--0001 00:00"), null);

assert.strictEqual(calculateDelayMinutes('08:30', '08:45'), 15);
assert.strictEqual(calculateDelayMinutes('08:45', '08:30'), -15);
assert.strictEqual(calculateDelayMinutes('23:50', '00:10'), 20); // Cross-midnight +20
assert.strictEqual(calculateDelayMinutes('00:10', '23:50'), -20); // Cross-midnight -20

assert.strictEqual(resolveMovementType('SUB', 'CGK'), 'Arrival');
assert.strictEqual(resolveMovementType('CGK', 'DPS'), 'Departure');
assert.strictEqual(resolveMovementType('', '', 'A SCHED'), 'Arrival');
assert.strictEqual(resolveMovementType('', '', 'D SCHED'), 'Departure');

const mockSheet = [
  ['Aeronautical Statistics', '', ''],
  ['Branch: CGK', '', ''],
  ['Period: 2026-08-01', '', ''],
  ['', '', ''],
  ['NO', 'FLT', 'AIRLINE', 'REG', 'ORIGIN', 'DEST', 'STD', 'ATD', 'DEP/ARR'],
  ['1', 'GA 100', 'GA', 'PK-GAA', 'CGK', 'DPS', 0.354166667, '08:45', 'D'],
  ['2', 'ID 200', 'ID', 'PK-LUI', 'SUB', 'CGK', '10:00', '09:40', 'A'],
  ['3', 'IU 300', 'IU', 'PK-SAJ', 'CGK', 'KNO', '12:00', '-', 'D'],
];
const headerMapping = findFDRHeaderRow(mockSheet);
assert.ok(headerMapping !== null);
assert.strictEqual(headerMapping.headerRowIndex, 4);

const records = parseFDRSheet(mockSheet, 'CGK', '2026-08-01');
assert.strictEqual(records.length, 3);
assert.strictEqual(records[0].airline, 'Garuda Indonesia');
assert.strictEqual(records[1].airline, 'Batik Air');
assert.strictEqual(records[2].status, 'CANCELLED');

const summary = aggregateHourlySlotWaves(records);
assert.strictEqual(summary.buckets.length, 24);
assert.strictEqual(summary.totalMovements, 3);

console.log('--- 2. Testing Indonesian Number Parsing ---');
assert.strictEqual(parseIndonesianNumber("1.242,86"), 1242.86);
assert.strictEqual(parseIndonesianNumber("-33,33"), -33.33);
assert.strictEqual(parseIndonesianNumber("71.8%"), 71.8);
assert.strictEqual(parseIndonesianNumber("nan %"), 0);

console.log('--- 3. Testing Suffix Dictionary & Extraction ---');
assert.strictEqual(OASYS_SUFFIX_DICTIONARY['X'].label, 'Extra Flight');
assert.strictEqual(OASYS_SUFFIX_DICTIONARY['R'].label, 'Return to Airport/Base (RTB/RTA)');
assert.strictEqual(OASYS_SUFFIX_DICTIONARY['D'].label, 'Divert');

const sX = extractFlightSuffix('GA120X');
assert.strictEqual(sX.base, 'GA120');
assert.strictEqual(sX.suffix, 'X');
assert.strictEqual(sX.suffixInfo?.label, 'Extra Flight');

const sPlain = extractFlightSuffix('QG682');
assert.strictEqual(sPlain.base, 'QG682');
assert.strictEqual(sPlain.suffix, '');
assert.strictEqual(sPlain.suffixInfo, null);

console.log('--- 4. Testing Airline Normalizer ---');
assert.strictEqual(normalizeAirline('QG', 'QG101').cleanName, 'Citilink');
assert.strictEqual(normalizeAirline('GAC', 'GAC202').cleanName, 'Citilink');
assert.strictEqual(normalizeAirline('IP', 'IP303').cleanName, 'Pelita Air');
assert.strictEqual(normalizeAirline('6D', '6D404').cleanName, 'Pelita Air');
assert.strictEqual(normalizeAirline('PAS', 'PAS505').cleanName, 'Pelita Air');
assert.strictEqual(normalizeAirline('8B', '8B606').cleanName, 'TransNusa');
assert.strictEqual(normalizeAirline('M8', 'M8707').cleanName, 'TransNusa');
assert.strictEqual(normalizeAirline('PK-TNC', '8B100').cleanName, 'TransNusa');
assert.strictEqual(normalizeAirline('QZ', 'QZ808').cleanName, 'Indonesia AirAsia');
assert.strictEqual(normalizeAirline('IL', 'IL909').cleanName, 'Trigana Air');
assert.strictEqual(normalizeAirline('IN', 'IN111').cleanName, 'Nam Air');
assert.strictEqual(normalizeAirline('2Y', '2Y222').cleanName, 'My Indo Airlines');
assert.strictEqual(normalizeAirline('FS', 'FS333').cleanName, 'Airfast Indonesia');
assert.strictEqual(normalizeAirline('PK-OCS', 'FS444').cleanName, 'Airfast Indonesia');

console.log('--- 5. Testing Auto-Detection Engine for All 7 OASYS Templates ---');

// Template 1: NORMAL
const normalHtml = `
<input type="hidden" name="BRANCH_CODE" value="CGK">
<input type="hidden" name="transactions_dateFDR" value="2026/08/01 - 2026/08/31">
<input type="hidden" name="OPERATOR" value="QG">
<input type="hidden" name="REAL" value="YES">
<table>
  <tr>
    <th>NO</th><th>AIR LINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th>
    <th>SIBT SOBT</th><th>AIBT AOBT</th><th>LEG</th><th>CITY 1</th><th>CITY 2</th>
    <th>MTOW</th><th>REG. NO</th><th>CAP.</th><th>LOAD</th><th>ADULT</th>
    <th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th>
    <th>MISS</th><th>CRW</th><th>EX. CRW</th><th>Umroh</th><th>CAR. (KG)</th>
    <th>BAGG. (KG)</th><th>POS (KG)</th><th>STAND</th><th>RUNWAY</th><th>FINAL</th>
    <th>FINAL TIME</th><th>BRANCH</th><th>EDIT</th>
  </tr>
  <tr>
    <td>1</td><td>Citilink</td><td>QG 101</td><td>QG 102</td><td>AIRBUS 320 200</td>
    <td>01-08-2026 07:00</td><td>01-08-2026 07:15</td><td>D<br>SCHED</td><td>CGK</td><td>DPS</td>
    <td>73.5</td><td>PK-GAA</td><td>180</td><td>160</td><td>150</td>
    <td>8</td><td>2</td><td>0</td><td>0</td><td>0</td>
    <td>0</td><td>6</td><td>2</td><td>0</td><td>2500</td>
    <td>1800</td><td>120</td><td>G01</td><td>25R</td><td>YES</td>
    <td>07:20</td><td>CGK</td><td></td>
  </tr>
</table>`;
assert.strictEqual(detectOasysFdrType(normalHtml, 'CGK QG FDR.xls'), 'NORMAL');

// Template 2: EX_HALIM
assert.strictEqual(detectOasysFdrType(normalHtml, 'CGK Halim Monitoring.xls'), 'EX_HALIM');
assert.strictEqual(detectOasysFdrType(normalHtml, 'HLP Transfer FDR.xls'), 'EX_HALIM');

// Template 3: LOAD_FACTOR
const loadFactorHtml = `
<table>
  <tr>
    <th>No</th><th>Airlines</th><th>Flight Number</th><th>Scheduled Time</th><th>Type of Aircraft</th>
    <th>Aircraft Reg No</th><th>Leg</th><th>Arrival Route</th><th>Departure Route</th><th>Seat Capacity</th>
    <th>Pax</th><th>SLF</th><th>Airport</th>
  </tr>
  <tr>
    <td>1</td><td>Garuda Indonesia</td><td>GA 100</td><td>08:00</td><td>738</td>
    <td>PK-GFA</td><td>D</td><td>DPS</td><td>CGK</td><td>162</td>
    <td>145</td><td>89.5 %</td><td>CGK</td>
  </tr>
  <tr><td colspan="13">PAX : ADULT + CHILD + TRANSIT + TRANSFER + EXTRA_CREW</td></tr>
</table>`;
assert.strictEqual(detectOasysFdrType(loadFactorHtml, 'CGK Load Factor.xls'), 'LOAD_FACTOR');

// Template 4: COMPARE_LOAD_FACTOR
const compareLfHtml = `
<title>DAU OASYS Comparison</title>
<table>
  <tr>
    <th>No</th><th>Airport</th>
    <th>Flight 2025</th><th>Flight 2026</th><th>Flight Ratio</th>
    <th>Pax 2025</th><th>Pax 2026</th><th>Pax Ratio</th>
    <th>Cargo 2025</th><th>Cargo 2026</th><th>Cargo Ratio</th>
    <th>SeatCap 2025</th><th>SeatCap 2026</th><th>SeatCap Ratio</th>
    <th>SLF 2025 SeatCap</th><th>SLF 2025 Pax</th><th>SEAT LOAD FACTOR 2025</th>
    <th>SLF 2026 SeatCap</th><th>SLF 2026 Pax</th><th>SEAT LOAD FACTOR 2026</th>
  </tr>
  <tr>
    <td>1</td><td>BDO</td>
    <td>100</td><td>120</td><td>20,00%</td>
    <td>15.000</td><td>18.000</td><td>20,00%</td>
    <td>5.000</td><td>4.500</td><td>-10,00%</td>
    <td>18.000</td><td>20.000</td><td>11,11%</td>
    <td>18.000</td><td>15.000</td><td>83,33%</td>
    <td>20.000</td><td>18.000</td><td>90,00%</td>
  </tr>
</table>`;
assert.strictEqual(detectOasysFdrType(compareLfHtml, 'DAU YoY Report.xls'), 'COMPARE_LOAD_FACTOR');

// Template 5: COMPARE_LOAD_FACTOR_DAY
const compareLfDayHtml = `
<title>DAU OASYS</title>
<table>
  <tr>
    <th>No</th><th>Airport</th>
    <th>Flight 2025</th><th>Flight 2026</th><th>Flight Ratio</th>
    <th>Pax 2025</th><th>Pax 2026</th><th>Pax Ratio</th>
    <th>Cargo 2025</th><th>Cargo 2026</th><th>Cargo Ratio</th>
    <th>SeatCap 2025</th><th>SeatCap 2026</th><th>SeatCap Ratio</th>
    <th>SLF 2025 SeatCap</th><th>SLF 2025 Pax</th><th>SEAT LOAD FACTOR 2025</th>
    <th>SLF 2026 SeatCap</th><th>SLF 2026 Pax</th><th>SEAT LOAD FACTOR 2026</th>
  </tr>
  <tr>
    <td>1</td><td>BDO <br>2026-10-15</td>
    <td>10</td><td>12</td><td>20%</td>
    <td>1500</td><td>1800</td><td>20%</td>
    <td>500</td><td>450</td><td>-10%</td>
    <td>1800</td><td>2000</td><td>11%</td>
    <td>1800</td><td>1500</td><td>83,3%</td>
    <td>2000</td><td>1800</td><td>90,0%</td>
  </tr>
</table>`;
assert.strictEqual(detectOasysFdrType(compareLfDayHtml, 'DAU Day Comparison.xls'), 'COMPARE_LOAD_FACTOR_DAY');

// Template 6: OASYS_VS_APPS
const appsHtml = `
<title>APPS VS FLIGHT DEP</title>
<table>
  <tr>
    <th>NO</th><th>AIRLINE</th><th>STAD</th><th>OFF BLOCK TIME</th><th>LEG</th>
    <th>D/I</th><th>CITY</th><th>ADULT</th><th>CHILD</th><th>INFANT</th>
    <th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISSED</th><th>CREW</th><th>EX.CREW</th>
  </tr>
  <tr>
    <td>1</td><td><a href="#">QG 822</a></td><td>06:00</td><td>06:10</td><td>D</td>
    <td>D</td><td>DPS</td><td>150</td><td>10</td><td>2</td>
    <td>0</td><td>0</td><td>0</td><td>0</td><td>6</td><td>0</td>
  </tr>
</table>`;
assert.strictEqual(detectOasysFdrType(appsHtml, 'Oasys Departure vs Apps.xls'), 'OASYS_VS_APPS');

// Template 7: OASYS_VS_EDIFLY
const ediflyHtml = `
<table>
  <tr>
    <th>NO</th><th>AIRLINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th>
    <th>SOBT/SIBT</th><th>AOBT/AIBT</th><th>LEG</th><th>STATION 1</th><th>STATION 2</th>
    <th>MTOW</th><th>REG. NO</th><th>API PAX EDIFLY</th><th>API CARGO EDIFLY</th>
  </tr>
  <tr>
    <td>1</td><td>Garuda</td><td>GA 100</td><td>GA 101</td><td>738</td>
    <td>08:00</td><td>08:05</td><td>D</td><td>CGK</td><td>DPS</td>
    <td>79000</td><td>PK-GAA</td><td>API INTEGRATION SUCCESS</td><td>NO INTEGRATION</td>
  </tr>
</table>`;
assert.strictEqual(detectOasysFdrType(ediflyHtml, 'Edifly Reconciliation.xls'), 'OASYS_VS_EDIFLY');

console.log('--- 6. Testing Pre-Scan Staging Metadata Extraction ---');
const preScan = preScanOasysFdr(normalHtml, 'CGK QG FDR.xls', 15000);
assert.strictEqual(preScan.reportType, 'NORMAL');
assert.strictEqual(preScan.branchCode, 'CGK');
assert.strictEqual(preScan.operator, 'QG');
assert.strictEqual(preScan.realization, 'YES');
assert.strictEqual(preScan.transactionsDateFdr, '2026/08/01 - 2026/08/31');
assert.strictEqual(preScan.totalRows, 1);

console.log('--- 7. Testing Dedicated Normal FDR Parser (Crew & Payload & Realization) ---');
const parsedNormal = parseNormalFDR(normalHtml, false, true, 360);
assert.strictEqual(parsedNormal.records.length, 1);
const r0 = parsedNormal.records[0];
assert.strictEqual(r0.operatingCrew, 8); // 6 CRW + 2 EX.CRW
assert.strictEqual(r0.totalPayloadKg, 4420); // 2500 + 1800 + 120
assert.strictEqual(r0.posKg, 120);
assert.strictEqual(r0.isFinal, true);
assert.strictEqual(r0.realized, true);
assert.strictEqual(parsedNormal.summary.crewPerFlight, 8);
assert.strictEqual(parsedNormal.summary.operatingCrew, 8);

// Test REAL = NO hold flight
const holdFlightHtml = `
<table>
  <tr><th>NO</th><th>AIR LINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th><th>SIBT SOBT</th><th>AIBT AOBT</th><th>LEG</th><th>CITY 1</th><th>CITY 2</th><th>MTOW</th><th>REG. NO</th><th>CAP.</th><th>LOAD</th><th>ADULT</th><th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISS</th><th>CRW</th><th>EX. CRW</th><th>Umroh</th><th>CAR. (KG)</th><th>BAGG. (KG)</th><th>POS (KG)</th><th>STAND</th><th>RUNWAY</th><th>FINAL</th><th>FINAL TIME</th><th>BRANCH</th><th>EDIT</th></tr>
  <tr><td>1</td><td>Batik</td><td>ID 777</td><td>-</td><td>A320</td><td>01-08-2026 12:00</td><td>30-11--0001 00:00</td><td>D</td><td>CGK</td><td>SUB</td><td>73.5</td><td>PK-LUJ</td><td>180</td><td>nan %</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>CGK-HOLD</td><td>-</td><td>NO</td><td>-</td><td>CGK</td><td></td></tr>
</table>`;
const parsedHold = parseNormalFDR(holdFlightHtml);
assert.strictEqual(parsedHold.records[0].status, 'HOLD_OR_CANCELLED');
assert.strictEqual(parsedHold.records[0].realized, false);
assert.strictEqual(parsedHold.summary.cancelledHoldFlights, 1);
assert.strictEqual(parsedHold.summary.realizedFlights, 0);

console.log('--- 8. Testing Load Factor Parser ---');
const parsedLf = parseLoadFactorFDR(loadFactorHtml);
assert.strictEqual(parsedLf.records.length, 1);
assert.strictEqual(parsedLf.records[0].slfPercent, 89.5);
assert.strictEqual(parsedLf.summary.totalCommercialPax, 145);
assert.strictEqual(parsedLf.summary.highSlfCount, 1);

console.log('--- 9. Testing Compare Load Factor YoY Parser ---');
const parsedCompare = parseCompareLoadFactorFDR(compareLfHtml);
assert.strictEqual(parsedCompare.records.length, 1);
assert.strictEqual(parsedCompare.records[0].airport, 'BDO');
assert.strictEqual(parsedCompare.records[0].flightPrevYear, 100);
assert.strictEqual(parsedCompare.records[0].flightCurrYear, 120);
assert.strictEqual(parsedCompare.summary.flightYoYGrowth, 20);

console.log('--- 10. Testing OASYS vs APPS Audit Parser ---');
const parsedApps = parseOasysVsAppsFDR(appsHtml);
assert.strictEqual(parsedApps.records.length, 1);
assert.strictEqual(parsedApps.records[0].flightNumber, 'QG 822');
assert.strictEqual(parsedApps.summary.fltDepOasys, 1);

console.log('--- 11. Testing OASYS vs EDIFLY Parser ---');
const parsedEdifly = parseOasysVsEdiflyFDR(ediflyHtml);
assert.strictEqual(parsedEdifly.records.length, 1);
assert.strictEqual(parsedEdifly.records[0].isPaxIntegrated, true);
assert.strictEqual(parsedEdifly.records[0].isCargoIntegrated, false);
assert.strictEqual(parsedEdifly.summary.paxIntegrationRate, 100);
assert.strictEqual(parsedEdifly.summary.cargoIntegrationRate, 0);

console.log('--- 12. Testing Multi-File HTML Merging ---');
const file1 = { name: 'CGK GA FDR.xls', content: normalHtml };
const file2 = {
  name: 'CGK ID FDR.xls',
  content: `<table><tr><th>NO</th><th>AIR LINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th><th>SIBT SOBT</th><th>AIBT AOBT</th><th>LEG</th><th>CITY 1</th><th>CITY 2</th><th>MTOW</th><th>REG. NO</th><th>CAP.</th><th>LOAD</th><th>ADULT</th><th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISS</th><th>CRW</th><th>EX. CRW</th><th>Umroh</th><th>CAR. (KG)</th><th>BAGG. (KG)</th><th>POS (KG)</th><th>STAND</th><th>RUNWAY</th><th>FINAL</th><th>FINAL TIME</th><th>BRANCH</th><th>EDIT</th></tr><tr><td>2</td><td>Batik</td><td>ID 200</td><td>ID 201</td><td>A320</td><td>01-08-2026 09:00</td><td>01-08-2026 09:05</td><td>A</td><td>SUB</td><td>CGK</td><td>73.5</td><td>PK-LAZ</td><td>180</td><td>170</td><td>160</td><td>10</td><td>0</td><td>0</td><td>0</td><td>0</td><td>0</td><td>6</td><td>0</td><td>0</td><td>1200</td><td>1400</td><td>0</td><td>T2-01</td><td>07L</td><td>YES</td><td>09:10</td><td>CGK</td><td></td></tr></table>`
};
const merged = mergeFdrHtmlFiles([file1, file2]);
assert.strictEqual(merged.filesProcessed, 2);
assert.strictEqual(merged.totalRowsMerged, 2);

console.log('--- 14. Testing HTML Comment Extraction for Edifly ---');
const ediflyCommentHtml = `
<table>
  <tr><th>NO</th><th>AIRLINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th><th>SOBT/SIBT</th><th>AOBT/AIBT</th><th>LEG</th><th>STATION 1</th><th>STATION 2</th><th>MTOW</th><th>REG. NO</th><th>API PAX EDIFLY</th><th>API CARGO EDIFLY</th></tr>
  <tr><td>1</td><td>Garuda</td><td>GA 100</td><td>GA 101</td><td>BOEING 737 800 (narrow)</td><td>01-09-2026 08:00</td><td>2026-09-01 08:10:00</td><td>D</td><td>CGK</td><td>DPS</td><td>79</td><td>PK-GAA</td><td><a href="compare_pax">INTEGRATED</a></td><td>NO INTEGRATION</td></tr>
</table>
<!-- <tr> <td colspan="13"><b>-PAX ALL -(789079)- | -Adult, Child, Infant (763814)- | -Transit(18307)-</b></td> <td align="right">Adult<br>753567</td> <td align="right">Child<br>8920</td> <td align="right">Infant<br>1327</td> <td align="right">Transit<br>18307</td> <td align="right">Transfer<br>6958</td> <td align="right">Crew<br>42812</td> <td align="right">Cargo(Kg)<br>8981968</td> <td align="right">Baggage(Kg)<br>6542100</td> <td align="right">Pos(Kg)<br>32100</td> <td align="right">Stand Use<br>5601</td> <td align="right">Runway Use<br>5601</td> </tr> -->
`;
import { extractEdiflyCommentTotals, detectFdrTemplateType, processFdrFiles } from '../src/utils/fdrParser.ts';
assert.strictEqual(typeof detectFdrTemplateType, 'function');

const commentTotals = extractEdiflyCommentTotals(ediflyCommentHtml);
assert.ok(commentTotals !== null);
assert.strictEqual(commentTotals.paxAll, 789079);
assert.strictEqual(commentTotals.adult, 753567);
assert.strictEqual(commentTotals.child, 8920);
assert.strictEqual(commentTotals.infant, 1327);
assert.strictEqual(commentTotals.transit, 18307);
assert.strictEqual(commentTotals.transfer, 6958);
assert.strictEqual(commentTotals.crew, 42812);
assert.strictEqual(commentTotals.cargoKg, 8981968);
assert.strictEqual(commentTotals.baggageKg, 6542100);
assert.strictEqual(commentTotals.posKg, 32100);
assert.strictEqual(commentTotals.standUse, 5601);
assert.strictEqual(commentTotals.runwayUse, 5601);

const parsedEdiflyWithComment = parseOasysVsEdiflyFDR(ediflyCommentHtml);
assert.strictEqual(parsedEdiflyWithComment.records.length, 1);
assert.strictEqual(parsedEdiflyWithComment.records[0].isPaxIntegrated, true);
assert.strictEqual(parsedEdiflyWithComment.records[0].apiPaxLink, 'compare_pax');
assert.strictEqual(parsedEdiflyWithComment.summary.commentTotals.paxAll, 789079);
assert.strictEqual(parsedEdiflyWithComment.summary.commentTotals.cargoKg, 8981968);

console.log('--- 15. Testing 0-Row EX_HALIM Empty State ---');
const zeroRowHalimHtml = `
<input type="hidden" name="BRANCH_CODE" value="CGK">
<input type="hidden" name="transactions_dateFDR" value="2026/09/01 - 2026/09/30">
<input type="hidden" name="OPERATOR" value="GA">
<input type="hidden" name="REAL" value="YES">
<table>
  <tr><th>NO</th><th>AIR LINE</th><th>FLIGHT NO</th><th>PAIRED NO</th><th>DESC</th><th>SIBT SOBT</th><th>AIBT AOBT</th><th>LEG</th><th>CITY 1</th><th>CITY 2</th><th>MTOW</th><th>REG. NO</th><th>CAP.</th><th>LOAD</th><th>ADULT</th><th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISS</th><th>CRW</th><th>EX. CRW</th><th>Umroh</th><th>CAR. (KG)</th><th>BAGG. (KG)</th><th>POS (KG)</th><th>STAND</th><th>RUNWAY</th><th>FINAL</th><th>FINAL TIME</th><th>BRANCH</th><th>EDIT</th></tr>
  <tr><td colspan="33"><b>-PAX ALL -(0)-</b></td></tr>
</table>
`;
assert.strictEqual(detectFdrTemplateType(zeroRowHalimHtml, 'GA/EX HALIM (CGK).xls'), 'EX_HALIM');
const parsedZeroHalim = parseNormalFDR(zeroRowHalimHtml, true);
assert.strictEqual(parsedZeroHalim.records.length, 0);
assert.strictEqual(parsedZeroHalim.summary.totalFlights, 0);
assert.strictEqual(parsedZeroHalim.summary.totalPax, 0);

console.log('--- 16. Testing OASYS vs APPS Nested Table Matrix Isolation ---');
const appsNestedHtml = `
<title>APPS VS FLIGHT DEP</title>
<table>
  <tr><th>NO</th><th>AIRLINE</th><th>STAD</th><th>OFF BLOCK TIME</th><th>LEG</th><th>D/I</th><th>CITY</th><th>ADULT</th><th>CHILD</th><th>INFANT</th><th>TRANSIT</th><th>TRANSFER</th><th>DIVERT</th><th>MISSED</th><th>CREW</th><th>EX.CREW</th></tr>
  <tr><td>1</td><td><a href="https://oasys.angkasapura2.co.id/integration3/pax/view/123">GA 682</a></td><td>06:00</td><td>06:15</td><td>D</td><td>D</td><td>DPS</td><td>150</td><td>10</td><td>2</td><td>0</td><td>0</td><td>0</td><td>0</td><td>6</td><td>2</td></tr>
  <tr><td colspan="16">
    <table border="1">
      <tr><th>Category</th><th>Normal Dom</th><th>Normal Int</th><th>Transit Dom</th><th>Transit Int</th></tr>
      <tr><td>FLT DEP</td><td>2325</td><td>474</td><td>0</td><td>0</td></tr>
      <tr><td>APPS</td><td>2317</td><td>472</td><td>0</td><td>0</td></tr>
      <tr><td>DEV</td><td>8</td><td>2</td><td>0</td><td>0</td></tr>
    </table>
    APPS = 2789 | FLIGHT DEPARTURE = 2799 | DEVIASI = 10
  </td></tr>
</table>
`;
const parsedNestedApps = parseOasysVsAppsFDR(appsNestedHtml);
assert.strictEqual(parsedNestedApps.records.length, 1);
assert.strictEqual(parsedNestedApps.records[0].airlineCode, 'GA');
assert.strictEqual(parsedNestedApps.records[0].flightNumber, 'GA 682');
assert.strictEqual(parsedNestedApps.records[0].linkHref, 'https://oasys.angkasapura2.co.id/integration3/pax/view/123');
assert.strictEqual(parsedNestedApps.summary.reconciliationMatrix.normalDom.fltDep, 2325);
assert.strictEqual(parsedNestedApps.summary.reconciliationMatrix.normalDom.apps, 2317);
assert.strictEqual(parsedNestedApps.summary.reconciliationMatrix.normalDom.dev, 8);
assert.strictEqual(parsedNestedApps.summary.reconciliationMatrix.normalInt.dev, 2);
assert.strictEqual(parsedNestedApps.summary.deviation, 10);
assert.strictEqual(parsedNestedApps.summary.fltDepOasys, 2799);
assert.strictEqual(parsedNestedApps.summary.appsRecorded, 2789);

console.log('--- 17. Testing Universal processFdrFiles Engine (ZIP & Multi-File) ---');
import JSZip from 'jszip';
const testZip = new JSZip();
testZip.file('Normal.xls', normalHtml);
testZip.file('Load Factor.xls', loadFactorHtml);
testZip.file('OASYS EDIFLY.xls', ediflyCommentHtml);
const zipBlob = await testZip.generateAsync({ type: 'nodebuffer' });

const procResult = await processFdrFiles([
  { name: 'GA.zip', content: zipBlob, size: zipBlob.length }
]);

assert.strictEqual(procResult.success, true);
assert.strictEqual(procResult.files.length, 3);
assert.ok(procResult.availableReportTypes.includes('NORMAL'));
assert.ok(procResult.availableReportTypes.includes('LOAD_FACTOR'));
assert.ok(procResult.availableReportTypes.includes('OASYS_VS_EDIFLY'));
assert.ok(procResult.reportData.NORMAL !== undefined);
assert.ok(procResult.reportData.LOAD_FACTOR !== undefined);
assert.ok(procResult.reportData.OASYS_VS_EDIFLY !== undefined);

console.log('\n======================================================');
console.log('SUCCESS: All Comprehensive FDR Unit Tests Passed 100%!');
console.log('======================================================');

