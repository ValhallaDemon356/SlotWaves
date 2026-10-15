import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { createClient } from '@supabase/supabase-js';
import JSZip from 'jszip';
import * as fdrEngine from '../../src/utils/fdrParser.ts';

window.Alpine = Alpine;
window.Chart = Chart;
window.createSupabaseClient = createClient;
window.JSZip = JSZip;
window.fdrEngine = fdrEngine;

Alpine.start();
