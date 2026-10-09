import assert from 'node:assert/strict';
import {
    formatIqd,
    formatNumber,
    formatNumberInput,
    parseNumber,
    parseNumberInput,
} from '../../resources/js/lib/numberFormat.js';

assert.equal(formatNumber(71555689), '71,555,689');
assert.equal(formatNumber(1000), '1,000');
assert.equal(formatNumber(null), '0');
assert.equal(formatIqd(71555689), '71,555,689 IQD');
assert.equal(formatIqd(1310000, 'دینار'), '1,310,000 دینار');
assert.equal(formatIqd(500, null), '500');

assert.equal(parseNumberInput('71,555,689'), '71555689');
assert.equal(parseNumber('71,555,689'), 71555689);
assert.equal(parseNumber(''), 0);

const live = formatNumberInput('71555689');
assert.equal(live.display, '71,555,689');
assert.equal(live.raw, '71555689');

const typed = formatNumberInput('71,555,6891');
assert.equal(typed.display, '715,556,891');
assert.equal(typed.raw, '715556891');

const empty = formatNumberInput('');
assert.equal(empty.display, '');
assert.equal(empty.raw, '');

const dec = formatNumberInput('1310.5', { allowDecimals: true });
assert.equal(dec.display, '1,310.5');
assert.equal(dec.raw, '1310.5');

console.log('numberFormat.test.mjs: ok');
