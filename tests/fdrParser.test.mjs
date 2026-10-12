import assert from 'node:assert';
import {
  parseExcelTime,
  parseExcelDate,
  calculateDelayMinutes,
  resolveMovementType,
  findFDRHeaderRow,
  parseFDRSheet,
  aggregateHourlySlotWaves
} from '../src/utils/fdrParser.ts';

// 1. Test parseExcelTime with decimal serials and string times
console.log('Testing parseExcelTime...');
assert.deepStrictEqual(parseExcelTime(0.354166667), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("0.354166667"), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("08:30"), { timeStr: '08:30', hour: 8 });
assert.deepStrictEqual(parseExcelTime("14:45:00"), { timeStr: '14:45', hour: 14 });
assert.deepStrictEqual(parseExcelTime("0615"), { timeStr: '06:15', hour: 6 });
assert.strictEqual(parseExcelTime("-"), null);
assert.strictEqual(parseExcelTime("N/A"), null);

// 2. Test calculateDelayMinutes with standard and cross-midnight times
console.log('Testing calculateDelayMinutes...');
assert.strictEqual(calculateDelayMinutes('08:30', '08:45'), 15);
assert.strictEqual(calculateDelayMinutes('08:45', '08:30'), -15);
assert.strictEqual(calculateDelayMinutes('23:50', '00:10'), 20); // +20 min cross midnight
assert.strictEqual(calculateDelayMinutes('00:10', '23:50'), -20); // -20 min early cross midnight

// 3. Test resolveMovementType
console.log('Testing resolveMovementType...');
assert.strictEqual(resolveMovementType('SUB', 'CGK'), 'Arrival');
assert.strictEqual(resolveMovementType('CGK', 'DPS'), 'Departure');
assert.strictEqual(resolveMovementType('', '', 'A SCHED'), 'Arrival');
assert.strictEqual(resolveMovementType('', '', 'D SCHED'), 'Departure');

// 4. Test dynamic header finding at rows 4-7
console.log('Testing findFDRHeaderRow...');
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

// 5. Test parseFDRSheet
console.log('Testing parseFDRSheet...');
const records = parseFDRSheet(mockSheet, 'CGK', '2026-08-01');
assert.strictEqual(records.length, 3);

// Record 1 (Garuda, Departure, on-time with 15m delay)
assert.strictEqual(records[0].airline, 'Garuda Indonesia');
assert.strictEqual(records[0].movementType, 'Departure');
assert.strictEqual(records[0].scheduledTime, '08:30');
assert.strictEqual(records[0].scheduledHour, 8);
assert.strictEqual(records[0].actualTime, '08:45');
assert.strictEqual(records[0].delayMinutes, 15);
assert.strictEqual(records[0].status, 'ON_TIME');

// Record 2 (Batik, Arrival, early by 20m)
assert.strictEqual(records[1].airline, 'Batik Air');
assert.strictEqual(records[1].movementType, 'Arrival');
assert.strictEqual(records[1].scheduledTime, '10:00');
assert.strictEqual(records[1].scheduledHour, 10);
assert.strictEqual(records[1].actualTime, '09:40');
assert.strictEqual(records[1].delayMinutes, -20);
assert.strictEqual(records[1].status, 'EARLY');

// Record 3 (Super Air Jet, cancelled/unrealized)
assert.strictEqual(records[2].airline, 'Super Air Jet');
assert.strictEqual(records[2].status, 'CANCELLED');

// 6. Test aggregateHourlySlotWaves
console.log('Testing aggregateHourlySlotWaves...');
const summary = aggregateHourlySlotWaves(records);
assert.strictEqual(summary.buckets.length, 24);
assert.strictEqual(summary.totalMovements, 3);
assert.strictEqual(summary.totalArrivals, 1);
assert.strictEqual(summary.totalDepartures, 2);
assert.strictEqual(summary.totalOnTime, 1);
assert.strictEqual(summary.totalEarly, 1);
assert.strictEqual(summary.totalCancelled, 1);

console.log('All FDR TypeScript parser unit tests passed successfully!');
