import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import { createClient } from '@supabase/supabase-js';

window.Alpine = Alpine;
window.Chart = Chart;
window.createSupabaseClient = createClient;

Alpine.start();


