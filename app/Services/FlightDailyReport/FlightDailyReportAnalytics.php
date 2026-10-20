<?php

namespace App\Services\FlightDailyReport;

use Carbon\Carbon;

class FlightDailyReportAnalytics
{
    public const INJOURNEY_AIRPORTS_COORDINATES = [
        // Domestic InJourney & Regional Airports
        'CGK' => ['lat' => -6.1256, 'lon' => 106.6559, 'lng' => 106.6559, 'name' => 'Soekarno-Hatta (Jakarta)', 'city' => 'Jakarta', 'country' => 'Indonesia'],
        'HLP' => ['lat' => -6.2667, 'lon' => 106.8917, 'lng' => 106.8917, 'name' => 'Halim Perdanakusuma (Jakarta)', 'city' => 'Jakarta', 'country' => 'Indonesia'],
        'KJT' => ['lat' => -6.6475, 'lon' => 108.1883, 'lng' => 108.1883, 'name' => 'Kertajati (Majalengka)', 'city' => 'Majalengka', 'country' => 'Indonesia'],
        'BDO' => ['lat' => -6.9006, 'lon' => 107.5761, 'lng' => 107.5761, 'name' => 'Husein Sastranegara (Bandung)', 'city' => 'Bandung', 'country' => 'Indonesia'],
        'SRG' => ['lat' => -6.9742, 'lon' => 110.3756, 'lng' => 110.3756, 'name' => 'Jenderal Ahmad Yani (Semarang)', 'city' => 'Semarang', 'country' => 'Indonesia'],
        'YIA' => ['lat' => -7.9072, 'lon' => 110.0544, 'lng' => 110.0544, 'name' => 'Yogyakarta International (Kulon Progo)', 'city' => 'Kulon Progo', 'country' => 'Indonesia'],
        'JOG' => ['lat' => -7.7881, 'lon' => 110.4317, 'lng' => 110.4317, 'name' => 'Adisutjipto (Yogyakarta)', 'city' => 'Yogyakarta', 'country' => 'Indonesia'],
        'SUB' => ['lat' => -7.3797, 'lon' => 112.7875, 'lng' => 112.7875, 'name' => 'Juanda (Surabaya)', 'city' => 'Surabaya', 'country' => 'Indonesia'],
        'BWX' => ['lat' => -8.3103, 'lon' => 114.3394, 'lng' => 114.3394, 'name' => 'Banyuwangi', 'city' => 'Banyuwangi', 'country' => 'Indonesia'],
        'DPS' => ['lat' => -8.7481, 'lon' => 115.1672, 'lng' => 115.1672, 'name' => 'I Gusti Ngurah Rai (Bali)', 'city' => 'Denpasar', 'country' => 'Indonesia'],
        'LOP' => ['lat' => -8.7578, 'lon' => 116.2764, 'lng' => 116.2764, 'name' => 'Lombok International (Praya)', 'city' => 'Praya', 'country' => 'Indonesia'],
        'KOE' => ['lat' => -10.1714, 'lon' => 123.6653, 'lng' => 123.6653, 'name' => 'El Tari (Kupang)', 'city' => 'Kupang', 'country' => 'Indonesia'],
        'KNO' => ['lat' => 3.6422, 'lon' => 98.8853, 'lng' => 98.8853, 'name' => 'Kualanamu (Medan)', 'city' => 'Medan', 'country' => 'Indonesia'],
        'BTJ' => ['lat' => 5.5225, 'lon' => 95.4194, 'lng' => 95.4194, 'name' => 'Sultan Iskandar Muda (Banda Aceh)', 'city' => 'Banda Aceh', 'country' => 'Indonesia'],
        'DTB' => ['lat' => 2.2575, 'lon' => 98.9839, 'lng' => 98.9839, 'name' => 'Sisingamangaraja XII (Silangit)', 'city' => 'Silangit', 'country' => 'Indonesia'],
        'PDG' => ['lat' => -0.7867, 'lon' => 100.2806, 'lng' => 100.2806, 'name' => 'Minangkabau (Padang)', 'city' => 'Padang', 'country' => 'Indonesia'],
        'PKU' => ['lat' => 0.4608, 'lon' => 101.4447, 'lng' => 101.4447, 'name' => 'Sultan Syarif Kasim II (Pekanbaru)', 'city' => 'Pekanbaru', 'country' => 'Indonesia'],
        'BTH' => ['lat' => 1.1211, 'lon' => 104.1189, 'lng' => 104.1189, 'name' => 'Hang Nadim (Batam)', 'city' => 'Batam', 'country' => 'Indonesia'],
        'TNJ' => ['lat' => 0.9236, 'lon' => 104.5317, 'lng' => 104.5317, 'name' => 'Raja Haji Fisabilillah (Tanjung Pinang)', 'city' => 'Tanjung Pinang', 'country' => 'Indonesia'],
        'DJB' => ['lat' => -1.6381, 'lon' => 103.6444, 'lng' => 103.6444, 'name' => 'Sultan Thaha (Jambi)', 'city' => 'Jambi', 'country' => 'Indonesia'],
        'PLM' => ['lat' => -2.8983, 'lon' => 104.7000, 'lng' => 104.7000, 'name' => 'Sultan Mahmud Badaruddin II (Palembang)', 'city' => 'Palembang', 'country' => 'Indonesia'],
        'PGK' => ['lat' => -2.1625, 'lon' => 106.1394, 'lng' => 106.1394, 'name' => 'Depati Amir (Pangkal Pinang)', 'city' => 'Pangkal Pinang', 'country' => 'Indonesia'],
        'TJQ' => ['lat' => -2.7608, 'lon' => 107.7547, 'lng' => 107.7547, 'name' => 'H.A.S. Hanandjoeddin (Belitung)', 'city' => 'Belitung', 'country' => 'Indonesia'],
        'BKS' => ['lat' => -3.8639, 'lon' => 102.3400, 'lng' => 102.3400, 'name' => 'Fatmawati Soekarno (Bengkulu)', 'city' => 'Bengkulu', 'country' => 'Indonesia'],
        'TKG' => ['lat' => -5.2425, 'lon' => 105.1786, 'lng' => 105.1786, 'name' => 'Radin Inten II (Lampung)', 'city' => 'Lampung', 'country' => 'Indonesia'],
        'PNK' => ['lat' => -0.1506, 'lon' => 109.4039, 'lng' => 109.4039, 'name' => 'Supadio (Pontianak)', 'city' => 'Pontianak', 'country' => 'Indonesia'],
        'BPN' => ['lat' => -1.2683, 'lon' => 116.8947, 'lng' => 116.8947, 'name' => 'Sultan Aji Muhammad Sulaiman Sepinggan (Balikpapan)', 'city' => 'Balikpapan', 'country' => 'Indonesia'],
        'BDJ' => ['lat' => -3.4422, 'lon' => 114.7578, 'lng' => 114.7578, 'name' => 'Syamsudin Noor (Banjarmasin)', 'city' => 'Banjarmasin', 'country' => 'Indonesia'],
        'PKY' => ['lat' => -2.2253, 'lon' => 113.9436, 'lng' => 113.9436, 'name' => 'Tjilik Riwut (Palangkaraya)', 'city' => 'Palangkaraya', 'country' => 'Indonesia'],
        'UPG' => ['lat' => -5.0617, 'lon' => 119.5542, 'lng' => 119.5542, 'name' => 'Sultan Hasanuddin (Makassar)', 'city' => 'Makassar', 'country' => 'Indonesia'],
        'MDC' => ['lat' => 1.5492, 'lon' => 124.9258, 'lng' => 124.9258, 'name' => 'Sam Ratulangi (Manado)', 'city' => 'Manado', 'country' => 'Indonesia'],
        'AMQ' => ['lat' => -3.7092, 'lon' => 128.0894, 'lng' => 128.0894, 'name' => 'Pattimura (Ambon)', 'city' => 'Ambon', 'country' => 'Indonesia'],
        'BIK' => ['lat' => -1.1903, 'lon' => 136.1097, 'lng' => 136.1097, 'name' => 'Frans Kaisiepo (Biak)', 'city' => 'Biak', 'country' => 'Indonesia'],
        'DJJ' => ['lat' => -2.5769, 'lon' => 140.5164, 'lng' => 140.5164, 'name' => 'Sentani (Jayapura)', 'city' => 'Jayapura', 'country' => 'Indonesia'],
        'PWL' => ['lat' => -7.4586, 'lon' => 109.0208, 'lng' => 109.0208, 'name' => 'Purwokerto / Wirasaba', 'city' => 'Purwokerto', 'country' => 'Indonesia'],
        'PCB' => ['lat' => -6.3475, 'lon' => 106.7497, 'lng' => 106.7497, 'name' => 'Pondok Cabe', 'city' => 'Tangerang Selatan', 'country' => 'Indonesia'],
        'SOQ' => ['lat' => -0.8906, 'lon' => 131.2872, 'lng' => 131.2872, 'name' => 'Domine Eduard Osok (Sorong)', 'city' => 'Sorong', 'country' => 'Indonesia'],
        'PLW' => ['lat' => -0.9178, 'lon' => 119.9097, 'lng' => 119.9097, 'name' => 'Mutiara SIS Al-Jufrie (Palu)', 'city' => 'Palu', 'country' => 'Indonesia'],
        'TIM' => ['lat' => -4.5283, 'lon' => 136.8867, 'lng' => 136.8867, 'name' => 'Mozes Kilangin (Timika)', 'city' => 'Timika', 'country' => 'Indonesia'],
        'MKQ' => ['lat' => -8.5203, 'lon' => 140.4181, 'lng' => 140.4181, 'name' => 'Mopah (Merauke)', 'city' => 'Merauke', 'country' => 'Indonesia'],
        'KDI' => ['lat' => -4.0817, 'lon' => 122.4178, 'lng' => 122.4178, 'name' => 'Haluoleo (Kendari)', 'city' => 'Kendari', 'country' => 'Indonesia'],
        'GTO' => ['lat' => 0.6372, 'lon' => 122.8517, 'lng' => 122.8517, 'name' => 'Djalaluddin (Gorontalo)', 'city' => 'Gorontalo', 'country' => 'Indonesia'],
        'LUW' => ['lat' => -1.0378, 'lon' => 122.7758, 'lng' => 122.7758, 'name' => 'Syukuran Aminuddin Amir (Luwuk)', 'city' => 'Luwuk', 'country' => 'Indonesia'],
        'TTE' => ['lat' => 0.8317, 'lon' => 127.3806, 'lng' => 127.3806, 'name' => 'Sultan Babullah (Ternate)', 'city' => 'Ternate', 'country' => 'Indonesia'],
        'LBJ' => ['lat' => -8.4878, 'lon' => 119.8872, 'lng' => 119.8872, 'name' => 'Komodo (Labuan Bajo)', 'city' => 'Labuan Bajo', 'country' => 'Indonesia'],
        'BMU' => ['lat' => -8.5397, 'lon' => 118.6903, 'lng' => 118.6903, 'name' => 'Sultan Muhammad Salahuddin (Bima)', 'city' => 'Bima', 'country' => 'Indonesia'],
        'AAP' => ['lat' => -0.3744, 'lon' => 117.2500, 'lng' => 117.2500, 'name' => 'APT Pranoto (Samarinda)', 'city' => 'Samarinda', 'country' => 'Indonesia'],
        'BEJ' => ['lat' => 2.1555, 'lon' => 117.4322, 'lng' => 117.4322, 'name' => 'Kalimarau (Berau)', 'city' => 'Berau', 'country' => 'Indonesia'],
        'BUU' => ['lat' => -1.1277, 'lon' => 102.1350, 'lng' => 102.1350, 'name' => 'Muara Bungo', 'city' => 'Bungo', 'country' => 'Indonesia'],
        'LLJ' => ['lat' => -3.2933, 'lon' => 102.9140, 'lng' => 102.9140, 'name' => 'Silampari (Lubuklinggau)', 'city' => 'Lubuklinggau', 'country' => 'Indonesia'],
        'MLG' => ['lat' => -7.9266, 'lon' => 112.7140, 'lng' => 112.7140, 'name' => 'Abdul Rachman Saleh (Malang)', 'city' => 'Malang', 'country' => 'Indonesia'],
        'PKN' => ['lat' => -2.7052, 'lon' => 111.6736, 'lng' => 111.6736, 'name' => 'Iskandar (Pangkalan Bun)', 'city' => 'Pangkalan Bun', 'country' => 'Indonesia'],
        'TRK' => ['lat' => 3.3267, 'lon' => 117.5656, 'lng' => 117.5656, 'name' => 'Juwata (Tarakan)', 'city' => 'Tarakan', 'country' => 'Indonesia'],
        'SMQ' => ['lat' => -2.5006, 'lon' => 112.9739, 'lng' => 112.9739, 'name' => 'Sampit', 'city' => 'Sampit', 'country' => 'Indonesia'],
        'SOC' => ['lat' => -7.5161, 'lon' => 110.7569, 'lng' => 110.7569, 'name' => 'Adi Soemarmo (Surakarta)', 'city' => 'Surakarta', 'country' => 'Indonesia'],
        'MOH' => ['lat' => -1.8744, 'lon' => 121.9483, 'lng' => 121.9483, 'name' => 'Morowali Airport', 'city' => 'Morowali', 'country' => 'Indonesia'],
        'MWS' => ['lat' => 0.8167, 'lon' => 116.8833, 'lng' => 116.8833, 'name' => 'Muara Wahau Airport', 'city' => 'Muara Wahau', 'country' => 'Indonesia'],
        'WDB' => ['lat' => 0.4639, 'lon' => 127.9150, 'lng' => 127.9150, 'name' => 'Wedo Bay Airport', 'city' => 'Weda', 'country' => 'Indonesia'],

        // International Destinations
        'SIN' => ['lat' => 1.3644, 'lon' => 103.9915, 'lng' => 103.9915, 'name' => 'Singapore Changi', 'city' => 'Singapore', 'country' => 'Singapore'],
        'KUL' => ['lat' => 2.7456, 'lon' => 101.7099, 'lng' => 101.7099, 'name' => 'Kuala Lumpur International', 'city' => 'Kuala Lumpur', 'country' => 'Malaysia'],
        'SZB' => ['lat' => 3.1306, 'lon' => 101.5497, 'lng' => 101.5497, 'name' => 'Sultan Abdul Aziz Shah', 'city' => 'Subang', 'country' => 'Malaysia'],
        'PEN' => ['lat' => 5.2971, 'lon' => 100.2769, 'lng' => 100.2769, 'name' => 'Penang International', 'city' => 'Penang', 'country' => 'Malaysia'],
        'BKK' => ['lat' => 13.6900, 'lon' => 100.7501, 'lng' => 100.7501, 'name' => 'Bangkok Suvarnabhumi', 'city' => 'Bangkok', 'country' => 'Thailand'],
        'DMK' => ['lat' => 13.9126, 'lon' => 100.6068, 'lng' => 100.6068, 'name' => 'Don Mueang International', 'city' => 'Bangkok', 'country' => 'Thailand'],
        'HKG' => ['lat' => 22.3080, 'lon' => 113.9185, 'lng' => 113.9185, 'name' => 'Hong Kong International', 'city' => 'Hong Kong', 'country' => 'Hong Kong'],
        'HND' => ['lat' => 35.5494, 'lon' => 139.7798, 'lng' => 139.7798, 'name' => 'Tokyo Haneda', 'city' => 'Tokyo', 'country' => 'Japan'],
        'NRT' => ['lat' => 35.7647, 'lon' => 140.3863, 'lng' => 140.3863, 'name' => 'Tokyo Narita', 'city' => 'Tokyo', 'country' => 'Japan'],
        'CTS' => ['lat' => 42.7752, 'lon' => 141.6923, 'lng' => 141.6923, 'name' => 'New Chitose Airport', 'city' => 'Sapporo', 'country' => 'Japan'],
        'KIX' => ['lat' => 34.4320, 'lon' => 135.2304, 'lng' => 135.2304, 'name' => 'Kansai International', 'city' => 'Osaka', 'country' => 'Japan'],
        'ICN' => ['lat' => 37.4602, 'lon' => 126.4407, 'lng' => 126.4407, 'name' => 'Seoul Incheon', 'city' => 'Seoul', 'country' => 'South Korea'],
        'PVG' => ['lat' => 31.1443, 'lon' => 121.8083, 'lng' => 121.8083, 'name' => 'Shanghai Pudong', 'city' => 'Shanghai', 'country' => 'China'],
        'CAN' => ['lat' => 23.3924, 'lon' => 113.2988, 'lng' => 113.2988, 'name' => 'Guangzhou Baiyun', 'city' => 'Guangzhou', 'country' => 'China'],
        'CSX' => ['lat' => 28.1892, 'lon' => 113.2197, 'lng' => 113.2197, 'name' => 'Changsha Huanghua International', 'city' => 'Changsha', 'country' => 'China'],
        'DYG' => ['lat' => 29.1028, 'lon' => 110.4439, 'lng' => 110.4439, 'name' => 'Zhangjiajie Hehua', 'city' => 'Zhangjiajie', 'country' => 'China'],
        'HAK' => ['lat' => 19.9347, 'lon' => 110.4589, 'lng' => 110.4589, 'name' => 'Haikou Meilan International', 'city' => 'Haikou', 'country' => 'China'],
        'KMG' => ['lat' => 25.1019, 'lon' => 102.9292, 'lng' => 102.9292, 'name' => 'Kunming Changshui International', 'city' => 'Kunming', 'country' => 'China'],
        'NNG' => ['lat' => 22.6083, 'lon' => 108.1722, 'lng' => 108.1722, 'name' => 'Nanning Wuxu International', 'city' => 'Nanning', 'country' => 'China'],
        'TPE' => ['lat' => 25.0797, 'lon' => 121.2342, 'lng' => 121.2342, 'name' => 'Taipei Taoyuan', 'city' => 'Taipei', 'country' => 'Taiwan'],
        'MFM' => ['lat' => 22.1494, 'lon' => 113.5917, 'lng' => 113.5917, 'name' => 'Macau International', 'city' => 'Macau', 'country' => 'Macau'],
        'HAN' => ['lat' => 21.2212, 'lon' => 105.8072, 'lng' => 105.8072, 'name' => 'Noi Bai International', 'city' => 'Hanoi', 'country' => 'Vietnam'],
        'SGN' => ['lat' => 10.8188, 'lon' => 106.6519, 'lng' => 106.6519, 'name' => 'Tan Son Nhat International', 'city' => 'Ho Chi Minh City', 'country' => 'Vietnam'],
        'PNH' => ['lat' => 11.5466, 'lon' => 104.8441, 'lng' => 104.8441, 'name' => 'Phnom Penh International', 'city' => 'Phnom Penh', 'country' => 'Cambodia'],
        'KOS' => ['lat' => 10.5797, 'lon' => 103.6360, 'lng' => 103.6360, 'name' => 'Sihanoukville International', 'city' => 'Sihanoukville', 'country' => 'Cambodia'],
        'SYD' => ['lat' => -33.9399, 'lon' => 151.1753, 'lng' => 151.1753, 'name' => 'Sydney Kingsford Smith', 'city' => 'Sydney', 'country' => 'Australia'],
        'MEL' => ['lat' => -37.6690, 'lon' => 144.8410, 'lng' => 144.8410, 'name' => 'Melbourne Tullamarine', 'city' => 'Melbourne', 'country' => 'Australia'],
        'PER' => ['lat' => -31.9403, 'lon' => 115.9668, 'lng' => 115.9668, 'name' => 'Perth Airport', 'city' => 'Perth', 'country' => 'Australia'],
        'DRW' => ['lat' => -12.4147, 'lon' => 130.8766, 'lng' => 130.8766, 'name' => 'Darwin International', 'city' => 'Darwin', 'country' => 'Australia'],
        'BNE' => ['lat' => -27.3842, 'lon' => 153.1175, 'lng' => 153.1175, 'name' => 'Brisbane Airport', 'city' => 'Brisbane', 'country' => 'Australia'],
        'BLR' => ['lat' => 13.1986, 'lon' => 77.7066, 'lng' => 77.7066, 'name' => 'Kempegowda International', 'city' => 'Bengaluru', 'country' => 'India'],
        'HYD' => ['lat' => 17.2403, 'lon' => 78.4294, 'lng' => 78.4294, 'name' => 'Rajiv Gandhi International', 'city' => 'Hyderabad', 'country' => 'India'],
        'JED' => ['lat' => 21.6796, 'lon' => 39.1565, 'lng' => 39.1565, 'name' => 'Jeddah King Abdulaziz', 'city' => 'Jeddah', 'country' => 'Saudi Arabia'],
        'MED' => ['lat' => 24.5534, 'lon' => 39.7051, 'lng' => 39.7051, 'name' => 'Medina Prince Mohammad', 'city' => 'Medina', 'country' => 'Saudi Arabia'],
        'DOH' => ['lat' => 25.2731, 'lon' => 51.6081, 'lng' => 51.6081, 'name' => 'Doha Hamad', 'city' => 'Doha', 'country' => 'Qatar'],
        'DXB' => ['lat' => 25.2532, 'lon' => 55.3657, 'lng' => 55.3657, 'name' => 'Dubai International', 'city' => 'Dubai', 'country' => 'United Arab Emirates'],
        'AUH' => ['lat' => 24.4330, 'lon' => 54.6511, 'lng' => 54.6511, 'name' => 'Zayed International', 'city' => 'Abu Dhabi', 'country' => 'United Arab Emirates'],
        'IST' => ['lat' => 41.2753, 'lon' => 28.7519, 'lng' => 28.7519, 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        'ISL' => ['lat' => 40.9769, 'lon' => 28.8146, 'lng' => 28.8146, 'name' => 'Ataturk Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
        'AMS' => ['lat' => 52.3105, 'lon' => 4.7683, 'lng' => 4.7683, 'name' => 'Amsterdam Schiphol', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
        'LHR' => ['lat' => 51.4700, 'lon' => -0.4543, 'lng' => -0.4543, 'name' => 'London Heathrow', 'city' => 'London', 'country' => 'United Kingdom'],
        'STN' => ['lat' => 51.8860, 'lon' => 0.2389, 'lng' => 0.2389, 'name' => 'London Stansted', 'city' => 'London', 'country' => 'United Kingdom'],
        'CDG' => ['lat' => 49.0097, 'lon' => 2.5479, 'lng' => 2.5479, 'name' => 'Paris Charles de Gaulle', 'city' => 'Paris', 'country' => 'France'],
        'FRA' => ['lat' => 50.0379, 'lon' => 8.5622, 'lng' => 8.5622, 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
        'MAD' => ['lat' => 40.4839, 'lon' => -3.5680, 'lng' => -3.5680, 'name' => 'Adolfo Suarez Madrid-Barajas', 'city' => 'Madrid', 'country' => 'Spain'],
    ];

    protected HourlyChartService $hourlyChartService;
    protected ReconciliationEngine $reconciliationEngine;

    public function __construct(
        HourlyChartService $hourlyChartService = null,
        ReconciliationEngine $reconciliationEngine = null
    ) {
        $this->hourlyChartService = $hourlyChartService ?: new HourlyChartService();
        $this->reconciliationEngine = $reconciliationEngine ?: new ReconciliationEngine();
    }

    /**
     * Compute comprehensive presentation-ready operational metrics from FDR records.
     */
    public function compute(array $records, array|string $meta = [], array|int $options = []): array
    {
        if (is_string($meta)) {
            $meta = ['airport' => $meta];
        }
        if (is_int($options)) {
            $options = ['report_mode' => $options];
        }

        $reportMode  = (int)($options['report_mode'] ?? 1);
        $airportCode = $meta['airport'] ?? 'CGK';
        $timeBasis   = $options['time_basis'] ?? 'scheduled';
        $reportDate  = $options['report_date'] ?? ($meta['period_start'] ?? '');

        // Section 11: Exclude PAX ALL summary rows from all analytics
        $records = array_values(array_filter($records, function ($r) {
            if (($r['row_type'] ?? 'MOVEMENT') === 'SUMMARY') return false;
            $al = trim($r['air_line'] ?? '');
            if (strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) return false;
            return true;
        }));

        // 1. 3 Mentor Hourly Charts (basis-aware)
        $hourlyCharts = $this->hourlyChartService->buildHourlyCharts(
            $records, $airportCode, $timeBasis, $reportDate
        );

        // 2. Top KPI Metric Cards (attaching peak hour)
        $kpis = $this->computeTopKpis($records);
        $kpis['peak_hour'] = $hourlyCharts['peak_hour'] ?? null;

        // 3. Schedule vs Realization Analysis (expanded)
        $schedVsReal = $this->computeScheduleVsRealization($records, (int)($options['otp_tolerance'] ?? 15));

        // 4. Passenger Analytics & Multi-Day Trend (expanded)
        $paxAnalytics = $this->computePassengerAnalytics($records);

        // 5. Airline & Route Performance
        $airlineRoute = $this->computeAirlineRoutePerformance($records);

        // 6. Fleet Performance (new)
        $fleetPerformance = $this->computeFleetPerformance($records);

        // 7. Ground Operations Enhanced (stand normalization + turnaround pairing)
        $groundOps = $this->computeGroundOpsEnhanced($records);

        // 8. Primary Combined Trend (Arrivals/Departures bar + Pax/Cargo line)
        $combinedTrend = $this->computeCombinedTrend($records, $options);

        // 8b. FDR Hourly Movement Distribution (strictly 24 buckets 00:00 to 23:00)
        $hourlyDistribution = $this->computeHourlyMovementDistribution($records, $options, $meta);
        $combinedTrend['hourly_distribution'] = $hourlyDistribution;

        // 9. Reconciliation Engine (Modes 7 & 8)
        $reconciliationApps   = $this->reconciliationEngine->reconcileOasysVsApps($records);
        $reconciliationEdifly = $this->reconciliationEngine->reconcileOasysVsEdifly($records);

        // 10. Mode Specific Prioritizations
        $modePayload = $this->buildModePayload($reportMode, $records, [
            'kpis'                  => $kpis,
            'hourly'                => $hourlyCharts,
            'pax'                   => $paxAnalytics,
            'ground'                => $groundOps,
            'combined_trend'        => $combinedTrend,
            'reconciliation_apps'   => $reconciliationApps,
            'reconciliation_edifly' => $reconciliationEdifly,
        ]);

        return [
            'meta'                    => $meta,
            'kpis'                    => $kpis,
            'kpi'                     => $kpis,
            'hourly_charts'           => $hourlyCharts,
            'hourly_distribution'     => $hourlyDistribution,
            'combined_trend'          => $combinedTrend,
            'schedule_vs_realization' => $schedVsReal,
            'sched_vs_real'           => $schedVsReal,
            'passenger_analytics'     => $paxAnalytics,
            'pax_analytics'           => $paxAnalytics,
            'airline_route'           => $airlineRoute,
            'airline_analysis'        => $airlineRoute['ranked_airlines'] ?? [],
            'airline_share'           => $airlineRoute['ranked_airlines'] ?? [],
            'fleet_performance'       => $fleetPerformance,
            'ground_operations'       => $groundOps,
            'ground_ops'              => $groundOps,
            'reconciliation_apps'     => $reconciliationApps,
            'reconciliation_edifly'   => $reconciliationEdifly,
            'mode_payload'            => $modePayload,
            'report_mode'             => $reportMode,
            'time_basis'              => $timeBasis,
        ];
    }

    /**
     * Top Metric Cards:
     * Total Flights, Arrivals, Departures, Total Passengers (Adult/Child/Infant),
     * Avg Load Factor, Cargo (KG), Baggage (KG), Irregularities (Divert, Miss, Unscheduled).
     */
    public function computeTopKpis(array $records): array
    {
        $totalFlights = count($records);
        $arrivals = 0;
        $departures = 0;
        $adult = 0;
        $child = 0;
        $infant = 0;
        $transit = 0;
        $transfer = 0;
        $totalCap = 0;
        $totalLoad = 0;
        $cargoKg = 0.0;
        $baggageKg = 0.0;
        $posKg = 0.0;
        $diverts = 0;
        $misses = 0;
        $unscheduled = 0;

        $operatingCrew = 0;
        $exCrew = 0;
        $totalMtowTon = 0.0;
        $umroh = 0;
        $cancelledHoldFlights = 0;
        $finalYes = 0;
        $finalNo = 0;

        $lfSum = 0.0;
        $lfCount = 0;

        $arrDom = 0;
        $arrInt = 0;
        $depDom = 0;
        $depInt = 0;
        $scheduledFlights = 0;
        $actualFlights = 0;

        foreach ($records as $r) {
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
            $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? ($r['dom_int'] ?? 'DOMESTIC'))));
            $isDom = in_array($tr, ['DOM', 'DOMESTIC', 'D'], true);

            if ($isArr) {
                $arrivals++;
                if ($isDom) $arrDom++; else $arrInt++;
            } else {
                $departures++;
                if ($isDom) $depDom++; else $depInt++;
            }

            $mvType = strtoupper(trim($r['movement_type'] ?? ($r['sched_type'] ?? '')));
            if (in_array($mvType, ['SCHEDULED', 'SCHED'], true)) {
                $scheduledFlights++;
            }
            $actAt = $r['actual_at'] ?? ($r['actual_time'] ?? ($r['aibt'] ?? ($r['aobt'] ?? null)));
            if (!empty($actAt) && $actAt !== 'N/A' && !str_contains((string)$actAt, '30-11--0001')) {
                $actualFlights++;
            }

            $rAdult = (int)($r['adult'] ?? ($r['pax_adult'] ?? 0));
            $rChild = (int)($r['child'] ?? ($r['pax_child'] ?? 0));
            $rInfant = (int)($r['infant'] ?? ($r['pax_infant'] ?? 0));
            $rTransit = (int)($r['transit'] ?? ($r['pax_transit'] ?? 0));
            $rTransfer = (int)($r['transfer'] ?? ($r['pax_transfer'] ?? 0));
            $rUmroh = (int)($r['umroh'] ?? 0);

            $adult += $rAdult;
            $child += $rChild;
            $infant += $rInfant;
            $transit += $rTransit;
            $transfer += $rTransfer;
            $umroh += $rUmroh;

            $cap = (int)($r['cap'] ?? ($r['capacity'] ?? 0));
            $load = (int)($r['load'] ?? ($r['pax_total'] ?? ($r['total_passenger'] ?? ($rAdult + $rChild + $rInfant))));
            $totalCap += $cap;
            $totalLoad += $load;

            if ($cap > 0) {
                $lfSum += (($load / $cap) * 100.0);
                $lfCount++;
            }

            $cargoKg += (float)($r['cargo_kg'] ?? 0.0);
            $baggageKg += (float)($r['baggage_kg'] ?? 0.0);
            $posKg += (float)($r['pos_kg'] ?? 0.0);

            // Crew calculation: CRW + EX. CRW
            $rCrw = (int)($r['crw'] ?? ($r['crew'] ?? 0));
            $rExCrw = (int)($r['ex_crw'] ?? ($r['ex_crew'] ?? 0));
            $rOpCrew = (int)($r['operating_crew'] ?? ($rCrw + $rExCrw));
            $operatingCrew += $rOpCrew;
            $exCrew += $rExCrw;

            // MTOW
            $rMtow = (float)($r['mtow_ton'] ?? (is_numeric($r['mtow'] ?? null) ? $r['mtow'] : 0.0));
            $totalMtowTon += $rMtow;

            // Realization and Hold/Cancelled
            $isHold = !empty($r['is_hold_or_cancelled']) || ($r['status'] ?? '') === 'HOLD_OR_CANCELLED';
            if ($isHold) {
                $cancelledHoldFlights++;
            }

            $fn = strtoupper(trim($r['final'] ?? ''));
            if ($fn === 'YES' || $fn === 'Y') $finalYes++;
            elseif ($fn === 'NO' || $fn === 'N') $finalNo++;

            $div = (int)($r['divert'] ?? 0);
            $ms = (int)($r['miss'] ?? 0);
            $diverts += $div;
            $misses += $ms;

            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'], true)) {
                $unscheduled++;
            }
        }

        // Strict Passenger Movement KPI: Adult + Child + Infant (Part 21 & 22)
        // Fallback to totalLoad only if detailed pax fields are completely absent (e.g. synthetic test fixtures)
        $detailedPax = $adult + $child + $infant;
        $passengerMovement = ($detailedPax > 0) ? $detailedPax : $totalLoad;

        // Pax per Flight (Part 23): e.g. 25,627 / 190 = 134.88... -> 134.9
        $paxPerFlight = ($totalFlights > 0) ? round($passengerMovement / $totalFlights, 1) : 0.0;

        // Passenger Seat Utilization: SUM(Adult + Child + Infant) / SUM(CAP) (Part 26)
        $passengerUtilization = ($totalCap > 0) ? round(($passengerMovement / $totalCap) * 100, 1) : null;
        $passengerUtilizationStr = ($passengerUtilization !== null) ? "{$passengerUtilization}%" : 'N/A';

        // Capacity-weighted Load Factor: SUM(CAP x LOAD%) / SUM(CAP) (Part 26)
        $weightedLoadFactor = ($totalCap > 0) ? round(($totalLoad / $totalCap) * 100, 1) : null;
        $weightedLoadFactorStr = ($weightedLoadFactor !== null) ? "{$weightedLoadFactor}%" : 'N/A';

        $cargoPerFlight = ($totalFlights > 0) ? round(($cargoKg / 1000) / $totalFlights, 2) : 0.0;
        $baggagePerFlight = ($totalFlights > 0) ? (int)round($baggageKg / $totalFlights) : 0;
        $irregularTotal = $diverts + $misses + $unscheduled;
        $irregularRate = ($totalFlights > 0) ? round(($irregularTotal / $totalFlights) * 100, 1) : 0.0;

        if ($actualFlights === 0 && $totalFlights > 0) {
            $actualFlights = max(0, $totalFlights - $cancelledHoldFlights);
        }

        // Operating Crew per Flight
        $totalCrew = $operatingCrew;
        $crewPerFlight = ($actualFlights > 0) ? round($totalCrew / $actualFlights, 1) : 0.0;

        // MTOW per Flight
        $avgMtowPerFlight = ($totalFlights > 0) ? round($totalMtowTon / $totalFlights, 1) : 0.0;

        // Total Payload
        $totalPayloadKg = $cargoKg + $baggageKg + $posKg;

        return [
            'total_flights'            => $totalFlights,
            'scheduled_flights'        => $scheduledFlights,
            'actual_flights'           => $actualFlights,
            'realized_flights'         => $actualFlights,
            'cancelled_hold_flights'   => $cancelledHoldFlights,
            'arrivals'                 => $arrivals,
            'total_arrivals'           => $arrivals,
            'departures'               => $departures,
            'total_departures'         => $departures,
            'arrivals_dom'             => $arrDom,
            'arrivals_int'             => $arrInt,
            'departures_dom'           => $depDom,
            'departures_int'           => $depInt,
            'total_passengers'         => $passengerMovement,
            'passenger_movement'       => $passengerMovement,
            'direct_passengers'        => $passengerMovement,
            'adult_passengers'         => $adult,
            'child_passengers'         => $child,
            'infant_passengers'        => $infant,
            'transit_passengers'       => $transit,
            'transfer_passengers'      => $transfer,
            'umroh_passengers'         => $umroh,
            'umroh'                    => $umroh,
            'pax_per_flight'           => $paxPerFlight,
            'pax_per_flight_display'   => "{$paxPerFlight} Pax / Flight",
            'total_capacity'           => $totalCap,
            'total_load'               => $totalLoad,
            'passenger_utilization'    => $passengerUtilizationStr,
            'passenger_utilization_num'=> $passengerUtilization,
            'weighted_load_factor'     => $weightedLoadFactorStr,
            'weighted_load_factor_num' => $weightedLoadFactor,
            'avg_load_factor'          => $weightedLoadFactorStr,
            'avg_load_factor_num'      => $weightedLoadFactor,
            'cargo_kg'                 => round($cargoKg, 1),
            'total_cargo_kg'           => round($cargoKg, 1),
            'cargo_ton'                => round($cargoKg / 1000, 2),
            'cargo_per_flight_t'       => $cargoPerFlight,
            'baggage_kg'               => round($baggageKg, 1),
            'total_baggage_kg'         => round($baggageKg, 1),
            'baggage_per_flight_kg'    => $baggagePerFlight,
            'pos_kg'                   => round($posKg, 1),
            'total_pos_kg'             => round($posKg, 1),
            'total_payload_kg'         => round($totalPayloadKg, 1),
            'operating_crew'           => $operatingCrew,
            'crew'                     => $operatingCrew,
            'ex_crew'                  => $exCrew,
            'total_crew'               => $totalCrew,
            'crew_per_flight'          => $crewPerFlight,
            'crew_per_flight_display'  => "{$crewPerFlight} Crew / Flight",
            'mtow_ton'                 => round($totalMtowTon, 1),
            'total_mtow_ton'           => round($totalMtowTon, 1),
            'avg_mtow_per_flight'      => $avgMtowPerFlight,
            'final_yes'                => $finalYes,
            'final_no'                 => $finalNo,
            'final_status'             => ($finalNo === 0 && $finalYes > 0) ? 'LOCKED / FINAL' : 'OPEN / UNLOCKED',
            'irregular_rate'           => $irregularRate,
            'irregularities'           => [
                'total'        => $irregularTotal,
                'divert'       => $diverts,
                'miss'         => $misses,
                'unscheduled'  => $unscheduled,
                'cancelled_hold' => $cancelledHoldFlights,
            ],
        ];
    }

    /**
     * Schedule vs Realization Analysis:
     * Compares SIBT/SOBT with AIBT/AOBT per flight.
     * OTP tolerance is configurable (default ±15 min per ICAO standard).
     */
    public function computeScheduleVsRealization(array $records, int $toleranceMinutes = 15): array
    {
        $delays          = [];   // individual delay_minutes for evaluated flights
        $earlyBeyond     = 0;   // early > tolerance
        $lateBeyond      = 0;   // late > tolerance
        $onTime          = 0;
        $totalEvaluated  = 0;
        $maxDelay        = null;
        $minDelay        = null;
        $top10           = [];   // largest delays (positive only)

        // Per-hour: scheduled count, actual count, avg variance (for plan vs actual chart)
        $hourlyVariances = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyVariances[$h] = ['hour' => $h, 'hour_label' => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00',
                'scheduled' => 0, 'actual' => 0, 'avg_variance' => 0, 'var_sum' => 0];
        }

        // Histogram bins (9 bins matching Schedule Variance Distribution)
        $histBins = [
            'early_gt60' => [
                'key'           => 'early_gt60',
                'label'         => 'EARLY > 60 min',
                'legacy_label'  => '<-60',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '>60 minutes early',
                'desc'          => '>60 minutes early',
                'count'         => 0,
                'min'           => PHP_INT_MIN,
                'max'           => -61,
                'color'         => '#1E3A8A', // dark blue
            ],
            'early_31_60' => [
                'key'           => 'early_31_60',
                'label'         => 'EARLY 31–60 min',
                'legacy_label'  => '-60~-31',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '31–60 minutes early',
                'desc'          => '31–60 minutes early',
                'count'         => 0,
                'min'           => -60,
                'max'           => -31,
                'color'         => '#2563EB', // medium blue
            ],
            'early_16_30' => [
                'key'           => 'early_16_30',
                'label'         => 'EARLY 16–30 min',
                'legacy_label'  => '-30~-16',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '16–30 minutes early',
                'desc'          => '16–30 minutes early',
                'count'         => 0,
                'min'           => -30,
                'max'           => -16,
                'color'         => '#38BDF8', // light blue
            ],
            'early_6_15' => [
                'key'           => 'early_6_15',
                'label'         => 'EARLY 6–15 min',
                'legacy_label'  => '-15~-6',
                'category'      => 'EARLY',
                'group'         => 'EARLY',
                'variance_desc' => '6–15 minutes early',
                'desc'          => '6–15 minutes early',
                'count'         => 0,
                'min'           => -15,
                'max'           => -6,
                'color'         => '#BAE6FD', // very light blue
            ],
            'on_time' => [
                'key'           => 'on_time',
                'label'         => 'ON TIME ±5 min',
                'legacy_label'  => '-5~5',
                'category'      => 'ON TIME',
                'group'         => 'ON TIME',
                'variance_desc' => 'Within ±5 minutes',
                'desc'          => 'Within ±5 minutes',
                'count'         => 0,
                'min'           => -5,
                'max'           => 5,
                'color'         => '#10B981', // green
            ],
            'late_6_15' => [
                'key'           => 'late_6_15',
                'label'         => 'LATE 6–15 min',
                'legacy_label'  => '6~15',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '6–15 minutes late',
                'desc'          => '6–15 minutes late',
                'count'         => 0,
                'min'           => 6,
                'max'           => 15,
                'color'         => '#FDE047', // light amber
            ],
            'late_16_30' => [
                'key'           => 'late_16_30',
                'label'         => 'LATE 16–30 min',
                'legacy_label'  => '16~30',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '16–30 minutes late',
                'desc'          => '16–30 minutes late',
                'count'         => 0,
                'min'           => 16,
                'max'           => 30,
                'color'         => '#F59E0B', // amber
            ],
            'late_31_60' => [
                'key'           => 'late_31_60',
                'label'         => 'LATE 31–60 min',
                'legacy_label'  => '31~60',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '31–60 minutes late',
                'desc'          => '31–60 minutes late',
                'count'         => 0,
                'min'           => 31,
                'max'           => 60,
                'color'         => '#EA580C', // orange/red
            ],
            'late_gt60' => [
                'key'           => 'late_gt60',
                'label'         => 'LATE >60 min',
                'legacy_label'  => '>60',
                'category'      => 'LATE',
                'group'         => 'LATE',
                'variance_desc' => '>60 minutes late',
                'desc'          => '>60 minutes late',
                'count'         => 0,
                'min'           => 61,
                'max'           => PHP_INT_MAX,
                'color'         => '#DC2626', // red
            ],
        ];

        foreach ($records as $r) {
            $isHold = !empty($r['is_hold_or_cancelled']) || ($r['status'] ?? '') === 'HOLD_OR_CANCELLED';
            if ($isHold) continue;

            $isRealized = !empty($r['is_realized']) || !empty($r['realization']) || (!empty($r['aibt']) && $r['aibt'] !== 'N/A' && !str_contains((string)$r['aibt'], '30-11--0001')) || (!empty($r['aobt']) && $r['aobt'] !== 'N/A' && !str_contains((string)$r['aobt'], '30-11--0001')) || isset($r['delay_minutes']);
            if (!$isRealized) continue;

            $delay = isset($r['delay_minutes']) ? (int)$r['delay_minutes'] : null;
            if ($delay === null) {
                // Try to compute from raw timestamps
                $isArr   = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
                $schedTs = $isArr ? ($r['sibt'] ?? ($r['arr_sched'] ?? '')) : ($r['sobt'] ?? ($r['dep_sched'] ?? ''));
                $actTs   = $isArr ? ($r['aibt'] ?? ($r['arr_actual'] ?? '')) : ($r['aobt'] ?? ($r['dep_actual'] ?? ''));
                if ($schedTs && $actTs && $schedTs !== 'N/A' && $actTs !== 'N/A') {
                    if (preg_match('/(\d{1,2}):(\d{2})/', $schedTs, $sm) && preg_match('/(\d{1,2}):(\d{2})/', $actTs, $am)) {
                        $delay = ((int)$am[1] * 60 + (int)$am[2]) - ((int)$sm[1] * 60 + (int)$sm[2]);
                    }
                }
            }
            if ($delay === null) continue;

            // Cross-midnight adjustment
            if ($delay < -720) {
                $delay += 1440;
            } elseif ($delay > 720) {
                $delay -= 1440;
            }

            $totalEvaluated++;
            $delays[] = $delay;
            if ($maxDelay === null || $delay > $maxDelay) $maxDelay = $delay;
            if ($minDelay === null || $delay < $minDelay) $minDelay = $delay;

            if (abs($delay) <= $toleranceMinutes) {
                $onTime++;
            } elseif ($delay < -$toleranceMinutes) {
                $earlyBeyond++;
            } else {
                $lateBeyond++;
                $top10[] = [
                    'flight_no' => $r['flight_no'] ?? 'N/A',
                    'route'     => $r['route']     ?? 'N/A',
                    'sched'     => (($r['direction'] ?? '') === 'ARRIVAL') ? ($r['sibt'] ?? 'N/A') : ($r['sobt'] ?? 'N/A'),
                    'actual'    => (($r['direction'] ?? '') === 'ARRIVAL') ? ($r['aibt'] ?? 'N/A') : ($r['aobt'] ?? 'N/A'),
                    'delay_min' => $delay,
                    'direction' => $r['direction'] ?? 'N/A',
                ];
            }

            // Hourly actual bucket (by sched hour)
            $isArr2 = ($r['direction'] ?? '') === 'ARRIVAL';
            $sTs   = $isArr2 ? ($r['sibt'] ?? '') : ($r['sobt'] ?? '');
            if ($sTs && $sTs !== 'N/A' && preg_match('/(\d{1,2}):/', $sTs, $hm)) {
                $sh = (int)$hm[1];
                if ($sh >= 0 && $sh < 24) {
                    $hourlyVariances[$sh]['scheduled']++;
                    $hourlyVariances[$sh]['actual']++;
                    $hourlyVariances[$sh]['var_sum'] += $delay;
                }
            }

            // Histogram (9 standard schedule variance bins)
            if ($delay < -60) {
                $histBins['early_gt60']['count']++;
            } elseif ($delay <= -31) {
                $histBins['early_31_60']['count']++;
            } elseif ($delay <= -16) {
                $histBins['early_16_30']['count']++;
            } elseif ($delay <= -6) {
                $histBins['early_6_15']['count']++;
            } elseif ($delay <= 5) {
                $histBins['on_time']['count']++;
            } elseif ($delay <= 15) {
                $histBins['late_6_15']['count']++;
            } elseif ($delay <= 30) {
                $histBins['late_16_30']['count']++;
            } elseif ($delay <= 60) {
                $histBins['late_31_60']['count']++;
            } else {
                $histBins['late_gt60']['count']++;
            }
        }

        for ($h = 0; $h < 24; $h++) {
            $act = $hourlyVariances[$h]['actual'];
            $hourlyVariances[$h]['avg_variance'] = ($act > 0) ? round($hourlyVariances[$h]['var_sum'] / $act, 1) : 0;
            unset($hourlyVariances[$h]['var_sum']);
        }

        // Scheduled hours distribution
        foreach ($records as $r) {
            $schedTime = (($r['direction'] ?? '') === 'ARRIVAL')
                ? ($r['sibt'] ?? ($r['arr_sched'] ?? 'N/A'))
                : ($r['sobt'] ?? ($r['dep_sched'] ?? 'N/A'));
            if ($schedTime && $schedTime !== 'N/A' && preg_match('/(\d{1,2}):(\d{2})/', $schedTime, $tm)) {
                $sh = (int)$tm[1];
                if ($sh >= 0 && $sh < 24) {
                    // only scheduled (not evaluated)
                }
            }
        }

        $hasEvaluated = ($totalEvaluated > 0);
        // Median
        $medianDelay = null;
        if ($hasEvaluated) {
            $sorted = $delays;
            sort($sorted);
            $mid = (int)floor(count($sorted) / 2);
            $medianDelay = (count($sorted) % 2 === 0)
                ? (($sorted[$mid - 1] + $sorted[$mid]) / 2.0)
                : (float)$sorted[$mid];
        }

        // Top 10 delays descending
        usort($top10, fn($a, $b) => $b['delay_min'] <=> $a['delay_min']);
        $top10 = array_slice($top10, 0, 10);

        $onTimePct    = $hasEvaluated ? round(($onTime / $totalEvaluated) * 100, 1) : null;
        $avgDelay     = $hasEvaluated ? round(array_sum($delays) / count($delays), 1) : null;

        $delaysFiltered = array_values(array_filter($delays, fn($d) => abs($d) <= 360));
        $avgDelayFiltered = count($delaysFiltered) > 0 ? round(array_sum($delaysFiltered) / count($delaysFiltered), 1) : $avgDelay;
        $maxDelayFiltered = count($delaysFiltered) > 0 ? max($delaysFiltered) : $maxDelay;
        $minDelayFiltered = count($delaysFiltered) > 0 ? min($delaysFiltered) : $minDelay;
        $outlierCount     = count($delays) - count($delaysFiltered);

        return [
            'has_evaluation'             => $hasEvaluated,
            'evaluated_count'            => $totalEvaluated,
            'evaluated_flights'          => $hasEvaluated ? $totalEvaluated : 'N/A',
            'tolerance_minutes'          => $toleranceMinutes,
            'on_time_count'              => $onTime,
            'early_count'                => $earlyBeyond,
            'late_count'                 => $lateBeyond,
            'on_time_percentage'         => $hasEvaluated ? "{$onTimePct}%" : 'N/A',
            'on_time_pct_num'            => $onTimePct ?? 0,
            'avg_delay_minutes'          => $hasEvaluated ? "{$avgDelay} min" : 'N/A',
            'avg_delay_num'              => $avgDelay ?? 0,
            'avg_delay_filtered_minutes' => $hasEvaluated ? "{$avgDelayFiltered} min" : 'N/A',
            'avg_delay_filtered_num'     => $avgDelayFiltered ?? 0,
            'max_delay_filtered_num'     => $maxDelayFiltered,
            'min_delay_filtered_num'     => $minDelayFiltered,
            'outlier_count'              => $outlierCount,
            'median_delay_num'      => $medianDelay,
            'median_delay'          => $hasEvaluated ? ($medianDelay >= 0 ? "+{$medianDelay}" : "{$medianDelay}") . ' min' : 'N/A',
            'max_delay_num'         => $maxDelay,
            'min_delay_num'         => $minDelay,
            'max_delay'             => $hasEvaluated ? ($maxDelay >= 0 ? "+{$maxDelay}" : "{$maxDelay}") . ' min' : 'N/A',
            'min_delay'             => $hasEvaluated ? ($minDelay >= 0 ? "+{$minDelay}" : "{$minDelay}") . ' min' : 'N/A',
            'title'                 => 'SCHEDULE VARIANCE DISTRIBUTION',
            'subtitle'              => 'How early or late actual movement occurred compared with schedule.',
            // legacy compat
            'minor_delay_count'     => $lateBeyond,
            'severe_delay_count'    => 0,
            'hourly_comparison'     => array_values($hourlyVariances),
            'histogram'             => array_values($histBins),
            'hist_bins'             => $histBins,
            'summary'               => [
                'early'   => $histBins['early_gt60']['count'] + $histBins['early_31_60']['count'] + $histBins['early_16_30']['count'] + $histBins['early_6_15']['count'],
                'on_time' => $histBins['on_time']['count'],
                'late'    => $histBins['late_6_15']['count'] + $histBins['late_16_30']['count'] + $histBins['late_31_60']['count'] + $histBins['late_gt60']['count'],
            ],
            'variance_summary'      => [
                'early_count'   => $histBins['early_gt60']['count'] + $histBins['early_31_60']['count'] + $histBins['early_16_30']['count'] + $histBins['early_6_15']['count'],
                'on_time_count' => $histBins['on_time']['count'],
                'late_count'    => $histBins['late_6_15']['count'] + $histBins['late_16_30']['count'] + $histBins['late_31_60']['count'] + $histBins['late_gt60']['count'],
            ],
            'top10_delays'          => $top10,
        ];
    }

    /**
     * Passenger Analytics & Payload Composition (expanded per B2 spec).
     * - Composition: ADU LT, CHI LD, INF ANT, TRAN SIT, CRW (from file — not fabricated).
     * - Breakdown ARR vs DEP per category.
     * - Load Factor: taken straight from the 'load' / 'cap' columns (or the LOAD column value).
     * - Payload: CAR.(KG), BAGG.(KG), POS(KG) per ARR/DEP.
     */
    public function computePassengerAnalytics(array $records): array
    {
        // Composition totals
        $comp = ['adult' => 0, 'child' => 0, 'infant' => 0, 'transit' => 0, 'transfer' => 0, 'crew' => 0];
        // Per-direction composition
        $compArr = $comp;
        $compDep = $comp;

        // Load Factor from file (as-is, do NOT recalculate)
        $lfFlights     = [];   // each element: ['lf' => float, 'dir' => 'ARR'|'DEP', 'flight_no' => ...]
        $lf0Count      = 0;
        $lfArrSum      = 0.0; $lfArrCount = 0;
        $lfDepSum      = 0.0; $lfDepCount = 0;
        $lfBuckets     = ['0pct' => 0, 'lt50' => 0, '50to69' => 0, '70to89' => 0, 'ge90' => 0];
        $lf0Flights    = [];

        // Payload per direction
        $payloadArr = ['cargo_kg' => 0.0, 'baggage_kg' => 0.0, 'pos_kg' => 0.0];
        $payloadDep = ['cargo_kg' => 0.0, 'baggage_kg' => 0.0, 'pos_kg' => 0.0];

        $dailyGroups = [];
        $totalLoad   = 0;

        foreach ($records as $r) {
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
            $a     = (int)($r['adult']    ?? ($r['pax_adult'] ?? 0));
            $c     = (int)($r['child']    ?? ($r['pax_child'] ?? 0));
            $inf   = (int)($r['infant']   ?? ($r['pax_infant'] ?? 0));
            $tr    = (int)($r['transit']  ?? ($r['pax_transit'] ?? 0));
            $tf    = (int)($r['transfer'] ?? ($r['pax_transfer'] ?? 0));
            $crw   = (int)($r['crw']      ?? ($r['crew'] ?? 0));
            $ld    = (int)($r['load']     ?? ($r['pax_total'] ?? ($a + $c + $inf)));

            $comp['adult']    += $a;
            $comp['child']    += $c;
            $comp['infant']   += $inf;
            $comp['transit']  += $tr;
            $comp['transfer'] += $tf;
            $comp['crew']     += $crw;
            $totalLoad        += $ld;

            $target = $isArr ? 'arr' : 'dep';
            ${'comp'.ucfirst($target)}['adult']    += $a;
            ${'comp'.ucfirst($target)}['child']    += $c;
            ${'comp'.ucfirst($target)}['infant']   += $inf;
            ${'comp'.ucfirst($target)}['transit']  += $tr;
            ${'comp'.ucfirst($target)}['transfer'] += $tf;
            ${'comp'.ucfirst($target)}['crew']     += $crw;

            // Load Factor (from file — read LOAD column directly)
            $cap   = (int)($r['cap'] ?? 0);
            // The LOAD field in the parsed record IS the load_factor from the OASYS file
            $lfVal = isset($r['load_factor']) ? (float)$r['load_factor'] : ($cap > 0 ? round(($ld / $cap) * 100, 1) : 0.0);

            $lfFlights[] = ['lf' => $lfVal, 'dir' => $isArr ? 'ARR' : 'DEP', 'flight_no' => $r['flight_no'] ?? ''];
            if ($lfVal == 0) {
                $lf0Count++;
                $lf0Flights[] = $r['flight_no'] ?? 'N/A';
            } elseif ($lfVal < 50) {
                $lfBuckets['lt50']++;
            } elseif ($lfVal < 70) {
                $lfBuckets['50to69']++;
            } elseif ($lfVal < 90) {
                $lfBuckets['70to89']++;
            } else {
                $lfBuckets['ge90']++;
            }

            if ($isArr) {
                $lfArrSum += $lfVal; $lfArrCount++;
                $payloadArr['cargo_kg']   += (float)($r['cargo_kg']   ?? 0);
                $payloadArr['baggage_kg'] += (float)($r['baggage_kg'] ?? 0);
                $payloadArr['pos_kg']     += (float)($r['pos_kg']     ?? 0);
            } else {
                $lfDepSum += $lfVal; $lfDepCount++;
                $payloadDep['cargo_kg']   += (float)($r['cargo_kg']   ?? 0);
                $payloadDep['baggage_kg'] += (float)($r['baggage_kg'] ?? 0);
                $payloadDep['pos_kg']     += (float)($r['pos_kg']     ?? 0);
            }

            $date = $r['flight_date'] ?? date('Y-m-d');
            if (!isset($dailyGroups[$date])) {
                $dailyGroups[$date] = ['date' => $date, 'flights' => 0, 'passengers' => 0, 'capacity' => 0, 'load' => 0];
            }
            $dailyGroups[$date]['flights']++;
            $dailyGroups[$date]['passengers'] += ($a + $c + $inf);
            $dailyGroups[$date]['capacity']   += $cap;
            $dailyGroups[$date]['load']       += $ld;
        }

        ksort($dailyGroups);
        $dailyTrend = [];
        foreach ($dailyGroups as $date => $dg) {
            $lf = ($dg['capacity'] > 0) ? round(($dg['load'] / $dg['capacity']) * 100, 1) : null;
            $dailyTrend[] = [
                'date' => $date, 'date_label' => date('d M', strtotime($date)),
                'flights' => $dg['flights'], 'volume' => $dg['passengers'],
                'capacity' => $dg['capacity'], 'load_factor' => $lf,
            ];
        }

        $totalAll     = $comp['adult'] + $comp['child'] + $comp['infant'] + $comp['transit'] + $comp['crew'];
        $hasBreakdown = ($totalAll > 0);

        return [
            'composition'       => $comp,
            'composition_arr'   => $compArr,
            'composition_dep'   => $compDep,
            'total_direct'      => $comp['adult'] + $comp['child'] + $comp['infant'],
            'total_all'         => $totalAll,
            'total_load'        => $totalLoad,
            'has_breakdown'     => $hasBreakdown,
            'daily_trend'       => $dailyTrend,
            'lf_from_file'      => true,   // signals UI: values taken as-is from file
            'lf_avg_arr'        => $lfArrCount > 0 ? round($lfArrSum / $lfArrCount, 1) : null,
            'lf_avg_dep'        => $lfDepCount > 0 ? round($lfDepSum / $lfDepCount, 1) : null,
            'lf_zero_count'     => $lf0Count,
            'lf_zero_flights'   => $lf0Flights,
            'lf_buckets'        => [
                ['label' => '0%',        'count' => $lf0Count,           'key' => '0pct'],
                ['label' => '< 50%',     'count' => $lfBuckets['lt50'],  'key' => 'lt50'],
                ['label' => '50–69%',    'count' => $lfBuckets['50to69'],'key' => '50to69'],
                ['label' => '70–89%',    'count' => $lfBuckets['70to89'],'key' => '70to89'],
                ['label' => '≥ 90%',     'count' => $lfBuckets['ge90'],  'key' => 'ge90'],
            ],
            'payload_arr'       => ['cargo_kg' => round($payloadArr['cargo_kg']), 'baggage_kg' => round($payloadArr['baggage_kg']), 'pos_kg' => round($payloadArr['pos_kg'])],
            'payload_dep'       => ['cargo_kg' => round($payloadDep['cargo_kg']), 'baggage_kg' => round($payloadDep['baggage_kg']), 'pos_kg' => round($payloadDep['pos_kg'])],
        ];
    }

    /**
     * Airline & Route Performance:
     * - Horizontal ranked bar of flights, passenger volume, cargo, and load factor by airline.
     * - Top routes matrix based on CITY 1 → CITY 2.
     */
    public function computeAirlineRoutePerformance(array $records): array
    {
        $airlines = [];
        $routes = [];
        $suffixCounts = [];

        foreach ($records as $r) {
            $al = $r['air_line_clean'] ?? ($r['air_line'] ?? 'N/A');
            $rawAl = $r['air_line'] ?? 'N/A';
            if (!isset($airlines[$al])) {
                $airlines[$al] = [
                    'airline'      => $al,
                    'raw_airline'  => $rawAl,
                    'code'         => $r['air_line_code'] ?? substr(strtoupper($rawAl), 0, 3),
                    'flights'      => 0,
                    'passengers'   => 0,
                    'capacity'     => 0,
                    'load'         => 0,
                    'cargo_kg'     => 0.0,
                ];
            }
            $airlines[$al]['flights']++;
            $airlines[$al]['passengers'] += ((int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0));
            $airlines[$al]['capacity'] += (int)($r['cap'] ?? 0);
            $airlines[$al]['load'] += (int)($r['load'] ?? 0);
            $airlines[$al]['cargo_kg'] += (float)($r['cargo_kg'] ?? 0.0);

            // Special Suffix Tracking
            $sfx = strtoupper(trim($r['flight_suffix'] ?? ''));
            if (!empty($sfx)) {
                if (!isset($suffixCounts[$sfx])) {
                    $suffixCounts[$sfx] = [
                        'suffix'   => $sfx,
                        'label'    => $r['flight_suffix_label'] ?? ($sfx . ' Suffix'),
                        'desc'     => $r['flight_suffix_desc'] ?? '',
                        'category' => $r['flight_suffix_category'] ?? 'Special',
                        'count'    => 0,
                        'flights'  => [],
                    ];
                }
                $suffixCounts[$sfx]['count']++;
                if (count($suffixCounts[$sfx]['flights']) < 5) {
                    $suffixCounts[$sfx]['flights'][] = $r['flight_no'] ?? 'N/A';
                }
            }

            $rt = $r['route'] ?? 'N/A';
            if ($rt !== 'N/A') {
                $orig = strtoupper(trim($r['city_1'] ?? ''));
                $dest = strtoupper(trim($r['city_2'] ?? ''));

                if (!isset($routes[$rt])) {
                    $routes[$rt] = [
                        'route'       => $rt,
                        'origin'      => $orig,
                        'dest'        => $dest,
                        'flights'     => 0,
                        'passengers'  => 0,
                        'capacity'    => 0,
                        'load'        => 0,
                        'delay_sum'       => 0,
                        'delay_count'     => 0,
                        'delayed_flights' => 0,
                        'delayedFlights'  => 0,
                        'origin_coord'=> self::INJOURNEY_AIRPORTS_COORDINATES[$orig] ?? null,
                        'dest_coord'  => self::INJOURNEY_AIRPORTS_COORDINATES[$dest] ?? null,
                    ];
                }
                $routes[$rt]['flights']++;
                $routes[$rt]['passengers'] += ((int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0));
                $routes[$rt]['capacity'] += (int)($r['cap'] ?? 0);
                $routes[$rt]['load'] += (int)($r['load'] ?? 0);
                if (isset($r['delay_minutes']) && is_numeric($r['delay_minutes'])) {
                    $dMin = (int)$r['delay_minutes'];
                    if ($dMin > 15) {
                        $routes[$rt]['delay_sum'] += $dMin;
                        $routes[$rt]['delay_count']++;
                        $routes[$rt]['delayed_flights']++;
                        $routes[$rt]['delayedFlights']++;
                    }
                }
            }
        }

        // Compute Leg Subtypes and Special Movements
        $schedCount = 0;
        $unschedCount = 0;
        $cargoCount = 0;
        $specialMovementsCount = 0;
        $specialSuffixLetters = ['X', 'R', 'I', 'D', 'A', 'F', 'H', 'V', 'G', 'M', 'O', 'L', '&', 'N', 'Z', 'S', 'T', 'K'];
        foreach ($records as $r) {
            $legVal = strtoupper(trim($r['leg'] ?? ($r['leg_raw'] ?? '')));
            $isUnsched = str_contains($legVal, 'UNSCHED');
            $isCargo = str_contains($legVal, 'CARGO');
            if ($isUnsched) $unschedCount++;
            elseif ($isCargo) $cargoCount++;
            else $schedCount++;

            $sfx = strtoupper(trim($r['flight_suffix'] ?? ''));
            if ($isUnsched || $isCargo || in_array($sfx, $specialSuffixLetters, true)) {
                $specialMovementsCount++;
            }
        }
        $legSubtypes = [
            'SCHED'   => $schedCount,
            'UNSCHED' => $unschedCount,
            'CARGO'   => $cargoCount,
        ];

        // Compute Load Factors and sort airlines by flight volume desc
        foreach ($airlines as &$a) {
            $a['avg_load_factor'] = ($a['capacity'] > 0) ? round(($a['load'] / $a['capacity']) * 100, 1) . '%' : 'N/A';
            $a['lf_num'] = ($a['capacity'] > 0) ? round(($a['load'] / $a['capacity']) * 100, 1) : 0;
            $a['cargo_kg'] = round($a['cargo_kg'], 1);
        }
        unset($a);
        usort($airlines, fn($x, $y) => $y['flights'] <=> $x['flights']);

        // Finalize route metrics with all aliases
        foreach ($routes as &$rt) {
            $lfNum = ($rt['capacity'] > 0) ? round(($rt['load'] / $rt['capacity']) * 100, 1) : 0.0;
            $rt['avg_load_factor'] = ($rt['capacity'] > 0) ? $lfNum . '%' : '0.0%';
            $rt['lf_num'] = $lfNum;
            $rt['avgLf'] = $lfNum;
            $rt['lf'] = $lfNum;
            $rt['slf'] = $rt['avg_load_factor'];

            $rt['count'] = $rt['flights'];
            $rt['movements'] = $rt['flights'];
            $rt['total_flights'] = $rt['flights'];

            $rt['pax'] = $rt['passengers'];
            $rt['totalPax'] = $rt['passengers'];
            $rt['totalCapacity'] = $rt['capacity'];

            $avgDel = ($rt['delayed_flights'] > 0) ? (int)round($rt['delay_sum'] / $rt['delayed_flights']) : 0;
            $rt['avg_delay_min'] = $avgDel;
            $rt['avg_delay'] = $avgDel;
            $rt['avgDelay'] = $avgDel;

            $dRate = ($rt['flights'] > 0) ? round(($rt['delayed_flights'] / $rt['flights']) * 100, 1) : 0.0;
            $rt['delay_rate'] = $dRate;
            $rt['delayRate'] = $dRate;
            $rt['delayPct'] = $dRate;
        }
        unset($rt);

        $allRoutes = array_values($routes);

        // Busiest Routes: sorted by flights desc
        $busiestRoutes = $allRoutes;
        usort($busiestRoutes, fn($x, $y) => $y['flights'] <=> $x['flights']);

        // Most Delayed Routes: sorted by delayed_flights / avg_delay_min desc (min 1 delayed flight)
        $delayedRoutes = array_filter($allRoutes, fn($r) => ($r['delayed_flights'] ?? 0) > 0);
        usort($delayedRoutes, function($x, $y) {
            $cmpDel = ($y['delayed_flights'] ?? 0) <=> ($x['delayed_flights'] ?? 0);
            if ($cmpDel !== 0) return $cmpDel;
            return ($y['avg_delay_min'] ?? 0) <=> ($x['avg_delay_min'] ?? 0);
        });

        // Highest SLF Routes: sorted by lf_num desc (min capacity > 0)
        $highestSlfRoutes = array_filter($allRoutes, fn($r) => $r['capacity'] > 0);
        usort($highestSlfRoutes, fn($x, $y) => $y['lf_num'] <=> $x['lf_num']);

        // Suffix breakdown sorted by count desc
        $suffixColors = [
            'X' => 'bg-blue-100 text-blue-700 border-blue-200',
            'R' => 'bg-rose-100 text-rose-700 border-rose-200',
            'I' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'D' => 'bg-amber-100 text-amber-700 border-amber-200',
            'A' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
            'F' => 'bg-teal-100 text-teal-700 border-teal-200',
            'H' => 'bg-purple-100 text-purple-700 border-purple-200',
            'O' => 'bg-cyan-100 text-cyan-700 border-cyan-200',
            'L' => 'bg-slate-100 text-slate-700 border-slate-200',
            '&' => 'bg-slate-100 text-slate-700 border-slate-200',
            'G' => 'bg-slate-100 text-slate-700 border-slate-200',
            'V' => 'bg-purple-100 text-purple-700 border-purple-200',
            'M' => 'bg-rose-100 text-rose-700 border-rose-200',
            'N' => 'bg-amber-100 text-amber-700 border-amber-200',
            'Z' => 'bg-sky-100 text-sky-700 border-sky-200',
            'S' => 'bg-orange-100 text-orange-700 border-orange-200',
            'T' => 'bg-orange-100 text-orange-700 border-orange-200',
            'K' => 'bg-orange-100 text-orange-700 border-orange-200',
        ];

        foreach ($suffixCounts as &$sfxItem) {
            $sfxItem['colorClass'] = $suffixColors[$sfxItem['suffix']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
        }
        unset($sfxItem);
        usort($suffixCounts, fn($x, $y) => $y['count'] <=> $x['count']);

        $networkCodes = [];
        $networkAirports = [];
        foreach ($allRoutes as $route) {
            foreach (['origin', 'dest'] as $endpoint) {
                $code = strtoupper(trim((string)($route[$endpoint] ?? '')));
                if ($code !== '' && $code !== 'N/A') {
                    $networkCodes[$code] = true;
                    if (!isset($networkAirports[$code]) && isset(self::INJOURNEY_AIRPORTS_COORDINATES[$code])) {
                        $c = self::INJOURNEY_AIRPORTS_COORDINATES[$code];
                        $networkAirports[$code] = [
                            'code'       => $code,
                            'name'       => $c['name'] ?? $code,
                            'city'       => $c['city'] ?? '',
                            'country'    => $c['country'] ?? '',
                            'lat'        => $c['lat'] ?? null,
                            'lon'        => $c['lon'] ?? ($c['lng'] ?? null),
                            'management' => 'OTHER',
                            'is_active'  => true,
                        ];
                    }
                }
            }
        }

        $mappedNetworkRoutes = 0;
        $unmappedRouteDetails = [];
        foreach ($allRoutes as $route) {
            $orig = $route['origin'] ?? '';
            $dest = $route['dest'] ?? ($route['destination'] ?? '');
            $hasOrig = !empty($networkAirports[$orig]['lat']) && !empty($networkAirports[$orig]['lon']);
            $hasDest = !empty($networkAirports[$dest]['lat']) && !empty($networkAirports[$dest]['lon']);
            if ($hasOrig && $hasDest) {
                $mappedNetworkRoutes++;
            } else {
                $reasons = [];
                if (!$hasOrig) $reasons[] = "Missing coordinates for origin [{$orig}]";
                if (!$hasDest) $reasons[] = "Missing coordinates for destination [{$dest}]";
                $unmappedRouteDetails[] = [
                    'route'   => "{$orig} → {$dest}",
                    'origin'  => $orig,
                    'dest'    => $dest,
                    'flights' => $route['flights'] ?? 0,
                    'reason'  => implode('; ', $reasons),
                ];
            }
        }

        $networkSummary = [
            'unique_routes'          => count($allRoutes),
            'unique_airports'        => count($networkCodes),
            'airports_with_coords'   => count(array_filter($networkAirports, fn($a) => $a['lat'] !== null && $a['lon'] !== null)),
            'mapped_routes'          => $mappedNetworkRoutes,
            'unmapped_routes'        => max(0, count($allRoutes) - $mappedNetworkRoutes),
            'unmapped_route_details' => $unmappedRouteDetails,
        ];

        return [
            'ranked_airlines'     => array_values($airlines),
            'top_routes'          => array_slice($busiestRoutes, 0, 15),
            'busiest_routes'      => array_slice($busiestRoutes, 0, 10),
            'top_busiest_routes'  => array_slice($busiestRoutes, 0, 10),
            'most_delayed_routes' => array_slice(array_values($delayedRoutes), 0, 10),
            'top_delayed_routes'  => array_slice(array_values($delayedRoutes), 0, 10),
            'highest_slf_routes'  => array_slice(array_values($highestSlfRoutes), 0, 10),
            'top_slf_routes'      => array_slice(array_values($highestSlfRoutes), 0, 10),
            'suffix_breakdown'    => array_values($suffixCounts),
            'suffix_distribution'=> array_values($suffixCounts),
            'leg_subtypes'        => $legSubtypes,
            'special_movements'   => $specialMovementsCount,
            'network_routes'      => $allRoutes,
            'network_airports'    => $networkAirports,
            'network_summary'     => $networkSummary,
            'airport_coordinates' => self::INJOURNEY_AIRPORTS_COORDINATES,
        ];
    }

    /**
     * Fleet Performance (new — B3 spec).
     * - Fleet mix per aircraft type (DESC column).
     * - Narrow vs Wide from "(N )" / "(W )" in DESC text.
     * - Top 10 registrations (REG. NO).
     */
    public function computeFleetPerformance(array $records): array
    {
        $fleetMap = [];   // keyed by desc
        $regMap   = [];   // keyed by reg_no
        $narrowCount = 0;
        $wideCount   = 0;

        foreach ($records as $r) {
            $desc  = trim($r['desc'] ?? ($r['aircraft_type'] ?? 'Unknown'));
            $regNo = trim($r['reg_no'] ?? ($r['registration'] ?? 'N/A'));
            $cap   = (int)($r['cap'] ?? 0);
            $lfVal = isset($r['load_factor']) ? (float)$r['load_factor'] : null;

            // Narrow/Wide from description text
            $isNarrow = str_contains($desc, '(N )') || str_contains($desc, '(N)');
            $isWide   = str_contains($desc, '(W )') || str_contains($desc, '(W)');
            if ($isNarrow) $narrowCount++;
            elseif ($isWide) $wideCount++;

            if (!isset($fleetMap[$desc])) {
                $fleetMap[$desc] = ['desc' => $desc, 'movements' => 0, 'cap_sum' => 0, 'cap_count' => 0, 'lf_sum' => 0.0, 'lf_count' => 0, 'narrow' => $isNarrow, 'wide' => $isWide];
            }
            $fleetMap[$desc]['movements']++;
            if ($cap > 0) { $fleetMap[$desc]['cap_sum'] += $cap; $fleetMap[$desc]['cap_count']++; }
            if ($lfVal !== null) { $fleetMap[$desc]['lf_sum'] += $lfVal; $fleetMap[$desc]['lf_count']++; }

            if ($regNo && $regNo !== 'N/A') {
                if (!isset($regMap[$regNo])) {
                    $regMap[$regNo] = ['reg_no' => $regNo, 'movements' => 0, 'desc' => $desc];
                }
                $regMap[$regNo]['movements']++;
            }
        }

        $fleetList = [];
        foreach ($fleetMap as $desc => $f) {
            $fleetList[] = [
                'desc'       => $desc,
                'movements'  => $f['movements'],
                'avg_cap'    => $f['cap_count'] > 0 ? round($f['cap_sum'] / $f['cap_count']) : null,
                'avg_lf'     => $f['lf_count']  > 0 ? round($f['lf_sum']  / $f['lf_count'], 1) : null,
                'narrow'     => $f['narrow'],
                'wide'       => $f['wide'],
            ];
        }
        usort($fleetList, fn($a, $b) => $b['movements'] <=> $a['movements']);

        $regList = array_values($regMap);
        usort($regList, fn($a, $b) => $b['movements'] <=> $a['movements']);
        $top10Regs = array_slice($regList, 0, 10);

        return [
            'fleet_mix'      => $fleetList,
            'narrow_count'   => $narrowCount,
            'wide_count'     => $wideCount,
            'total_regs'     => count($regMap),
            'regs_multi'     => count(array_filter($regMap, fn($r) => $r['movements'] > 1)),
            'top10_regs'     => $top10Regs,
        ];
    }

    /**
     * Ground Operations Enhanced (B4 spec):
     * - Normalize STAND / RUN WAY (strip airport prefix + dash).
     * - Turnaround pairing: FIFO greedy per REG_NO (ARR→DEP pairs).
     * - Ground time stats + checksum.
     */
    public function computeGroundOpsEnhanced(array $records): array
    {
        $airportPrefix = ''; // detected from first stand value
        $stands  = [];
        $runways = [];
        $standFilled   = 0;
        $runwayFilled  = 0;
        $totalFlights  = max(1, count($records));

        // Collect and normalize stand/runway
        foreach ($records as $r) {
            $rawStand  = $r['stand']  ?? '';
            $rawRunway = $r['runway'] ?? '';

            // Auto-detect prefix (e.g. "CGK-")
            if (!$airportPrefix && preg_match('/^([A-Z]{2,4})-/', (string)$rawStand, $pm)) {
                $airportPrefix = $pm[0]; // e.g. "CGK-"
            }

            $normStand  = $this->normalizeGround($rawStand,  $airportPrefix);
            $normRunway = $this->normalizeGround($rawRunway, $airportPrefix);

            if ($normStand !== '' && $normStand !== 'N/A') {
                $stands[$normStand] = ($stands[$normStand] ?? 0) + 1;
                $standFilled++;
            }
            if ($normRunway !== '' && $normRunway !== 'N/A') {
                $runways[$normRunway] = ($runways[$normRunway] ?? 0) + 1;
                $runwayFilled++;
            }
        }

        arsort($stands);
        arsort($runways);

        $standList = [];
        foreach ($stands as $stand => $cnt) {
            $standList[] = ['stand' => $stand, 'count' => $cnt, 'percentage' => round(($cnt / $totalFlights) * 100, 1)];
        }
        $runwayList = [];
        foreach ($runways as $rw => $cnt) {
            $runwayList[] = ['runway' => $rw, 'count' => $cnt, 'percentage' => round(($cnt / $totalFlights) * 100, 1)];
        }

        // ── Turnaround Pairing: FIFO greedy per REG_NO ─────────────────────
        // Sort all records by actual time (AIBT for ARR, AOBT for DEP)
        $byReg = [];
        foreach ($records as $r) {
            $regNo = trim($r['reg_no'] ?? '');
            if (!$regNo || $regNo === 'N/A') continue;
            $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
            $ts    = $isArr ? ($r['aibt'] ?? '') : ($r['aobt'] ?? '');
            if (!$ts || $ts === 'N/A') continue;
            // Convert HH:MM to minutes since midnight
            if (!preg_match('/(\d{1,2}):(\d{2})/', (string)$ts, $tm)) continue;
            $tsMin = (int)$tm[1] * 60 + (int)$tm[2];

            $byReg[$regNo][] = [
                'is_arr'   => $isArr,
                'ts_min'   => $tsMin,
                'ts_str'   => $ts,
                'stand'    => $this->normalizeGround($r['stand'] ?? '', $airportPrefix),
                'raw'      => $r,
            ];
        }

        $pairs          = [];   // completed turnaround pairs
        $unpaired       = [];   // flights without a pair
        $groundTimes    = [];   // minutes
        $standChanges   = 0;

        foreach ($byReg as $reg => $events) {
            // Sort by actual timestamp
            usort($events, fn($a, $b) => $a['ts_min'] <=> $b['ts_min']);

            $arrQueue = [];  // unmatched ARR events (FIFO)
            foreach ($events as $ev) {
                if ($ev['is_arr']) {
                    $arrQueue[] = $ev;
                } else {
                    // Find earliest unmatched ARR that is before this DEP
                    $matched = null;
                    foreach ($arrQueue as $qi => $qa) {
                        if ($qa['ts_min'] <= $ev['ts_min']) {
                            $matched = $qi;
                            break;
                        }
                    }
                    if ($matched !== null) {
                        $arr = $arrQueue[$matched];
                        array_splice($arrQueue, $matched, 1);
                        $groundMin = $ev['ts_min'] - $arr['ts_min'];
                        $groundTimes[] = $groundMin;
                        if ($arr['stand'] !== $ev['stand'] && $arr['stand'] !== '' && $ev['stand'] !== '') {
                            $standChanges++;
                        }
                        $pairs[] = [
                            'reg_no'       => $reg,
                            'arr_time'     => $arr['ts_str'],
                            'dep_time'     => $ev['ts_str'],
                            'ground_min'   => $groundMin,
                            'stand_arr'    => $arr['stand'],
                            'stand_dep'    => $ev['stand'],
                            'stand_change' => ($arr['stand'] !== $ev['stand']),
                        ];
                    } else {
                        $unpaired[] = array_merge($ev, ['reg_no' => $reg, 'reason' => 'no_arr']);
                    }
                }
            }
            // Remaining ARRs without a DEP
            foreach ($arrQueue as $qa) {
                $unpaired[] = array_merge($qa, ['reg_no' => $reg, 'reason' => 'no_dep']);
            }
        }

        // Ground time statistics
        $gtCount  = count($groundTimes);
        $gtMin    = $gtCount > 0 ? min($groundTimes) : null;
        $gtMax    = $gtCount > 0 ? max($groundTimes) : null;
        $gtMean   = $gtCount > 0 ? round(array_sum($groundTimes) / $gtCount, 1) : null;
        $gtMedian = null;
        if ($gtCount > 0) {
            $sorted = $groundTimes;
            sort($sorted);
            $mid = (int)floor($gtCount / 2);
            $gtMedian = ($gtCount % 2 === 0)
                ? round(($sorted[$mid - 1] + $sorted[$mid]) / 2.0, 1)
                : (float)$sorted[$mid];
        }

        // Ground time histogram
        $gtHist = [];
        foreach ([0,30,60,90,120,180,240] as $b) {
            $next = ($b === 240) ? PHP_INT_MAX : $b + 30;
            $label = ($b === 240) ? '240+ min' : "{$b}–" . ($next - 1) . ' min';
            $gtHist[] = ['label' => $label, 'min' => $b, 'max' => $next, 'count' => 0];
        }
        foreach ($groundTimes as $gt) {
            foreach ($gtHist as &$bucket) {
                if ($gt >= $bucket['min'] && $gt < $bucket['max']) { $bucket['count']++; break; }
            }
        }
        unset($bucket);

        // Irregularity details (unchanged)
        $irregularDetails = ['divert_flights' => [], 'miss_flights' => [], 'unscheduled_flights' => []];
        foreach ($records as $r) {
            if ((int)($r['divert'] ?? 0) > 0) $irregularDetails['divert_flights'][] = $r;
            if ((int)($r['miss'] ?? 0) > 0)   $irregularDetails['miss_flights'][] = $r;
            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'])) $irregularDetails['unscheduled_flights'][] = $r;
        }

        return [
            'stands'             => $standList,
            'runways'            => $runwayList,
            'stand_unique'       => count($stands),
            'stand_filled'       => $standFilled,
            'runway_filled'      => $runwayFilled,
            'turnaround_pairs'   => $pairs,
            'turnaround_count'   => $gtCount,
            'unpaired_flights'   => count($unpaired),
            'stand_changes'      => $standChanges,
            'ground_time_min'    => $gtMin,
            'ground_time_median' => $gtMedian,
            'ground_time_mean'   => $gtMean,
            'ground_time_max'    => $gtMax,
            'ground_time_histogram' => $gtHist,
            'irregularities'     => $irregularDetails,
        ];
    }

    /**
     * Normalize a stand or runway string: remove airport prefix + dash.
     * E.g. "CGK-G39" -> "G39", "BTJ-4" -> "4".
     */
    protected function normalizeGround(string $raw, string $prefix): string
    {
        $v = trim($raw);
        if ($v === '' || $v === 'N/A') return $v;
        if ($prefix && str_starts_with($v, $prefix)) {
            $v = substr($v, strlen($prefix));
        }
        // Also strip leading "XXX-" pattern generically
        $v = preg_replace('/^[A-Z]{2,4}-/', '', $v);
        return trim($v);
    }

    /**
     * Ground Operations & Irregularities (legacy method — kept for backward compat).
     * Delegates to computeGroundOpsEnhanced().
     */
    public function computeGroundOps(array $records): array
    {
        $stands = [];
        $runways = [];
        $totalFlights = max(1, count($records));

        $irregularDetails = [
            'divert_flights'      => [],
            'miss_flights'        => [],
            'unscheduled_flights' => [],
        ];

        foreach ($records as $r) {
            $st = $r['stand'] ?? 'N/A';
            if ($st !== 'N/A' && $st !== '') {
                $stands[$st] = ($stands[$st] ?? 0) + 1;
            }

            $rw = $r['runway'] ?? 'N/A';
            if ($rw !== 'N/A' && $rw !== '') {
                $runways[$rw] = ($runways[$rw] ?? 0) + 1;
            }

            if ((int)($r['divert'] ?? 0) > 0) {
                $irregularDetails['divert_flights'][] = $r;
            }
            if ((int)($r['miss'] ?? 0) > 0) {
                $irregularDetails['miss_flights'][] = $r;
            }
            if (in_array($r['sched_type'] ?? '', ['UNSCHED', 'UNSCHEDULED'], true)) {
                $irregularDetails['unscheduled_flights'][] = $r;
            }
        }

        arsort($stands);
        arsort($runways);

        $standList = [];
        foreach ($stands as $stand => $cnt) {
            $standList[] = [
                'stand'      => $stand,
                'count'      => $cnt,
                'percentage' => round(($cnt / $totalFlights) * 100, 1),
            ];
        }

        $runwayList = [];
        foreach ($runways as $rw => $cnt) {
            $runwayList[] = [
                'runway'     => $rw,
                'count'      => $cnt,
                'percentage' => round(($cnt / $totalFlights) * 100, 1),
            ];
        }

        return [
            'stands'          => $standList,
            'runways'         => $runwayList,
            'irregularities'  => $irregularDetails,
        ];
    }

    /**
     * Build Mode Specific Prioritizations for Modes 1..8.
     */
    public function buildModePayload(int $reportMode, array $records, array $context): array
    {
        switch ($reportMode) {
            case 2: // LOAD FACTOR (Capacity vs load priority)
                return $this->buildMode2LoadFactor($records);

            case 3: // COMPARE LOAD FACTOR (Period vs period comparison)
                return $this->buildMode3ComparePeriod($records);

            case 4: // COMPARE LOAD FACTOR DAY (Day-of-week comparison)
                return $this->buildMode4CompareDayOfWeek($records);

            case 5: // AIR TRAFFIC MONITORING I (Hourly operational movement priority)
                return [
                    'mode'        => 5,
                    'mode_name'   => 'AIR TRAFFIC MONITORING I',
                    'description' => 'Hourly operational flow analysis with peak index and capacity constraints.',
                    'hourly'      => $context['hourly'],
                ];

            case 6: // AIR TRAFFIC MONITORING II (Ground/stand & runway priority)
                return [
                    'mode'        => 6,
                    'mode_name'   => 'AIR TRAFFIC MONITORING II',
                    'description' => 'Ground stand distribution, runway assignment balance, and turnaround logistics.',
                    'stands'      => $context['ground']['stands'],
                    'runways'     => $context['ground']['runways'],
                ];

            case 7: // OASYS VS APPS
                return [
                    'mode'           => 7,
                    'mode_name'      => 'OASYS VS APPS',
                    'reconciliation' => $context['reconciliation_apps'],
                ];

            case 8: // OASYS VS EDIFLY
                return [
                    'mode'           => 8,
                    'mode_name'      => 'OASYS VS EDIFLY',
                    'reconciliation' => $context['reconciliation_edifly'],
                ];

            case 1:
            default: // NORMAL
                return [
                    'mode'        => 1,
                    'mode_name'   => 'NORMAL',
                    'description' => 'Standard holistic operational dashboard with full aviation telemetry.',
                ];
        }
    }

    /**
     * Mode 2: LOAD FACTOR
     */
    protected function buildMode2LoadFactor(array $records): array
    {
        $high = 0;   // >= 85%
        $normal = 0; // 70-84%
        $low = 0;    // < 70%
        $zeroCap = 0;

        foreach ($records as $r) {
            $cap = (int)($r['cap'] ?? 0);
            $load = (int)($r['load'] ?? 0);
            if ($cap <= 0) {
                $zeroCap++;
                continue;
            }
            $lf = ($load / $cap) * 100;
            if ($lf >= 85) $high++;
            elseif ($lf >= 70) $normal++;
            else $low++;
        }

        return [
            'mode'           => 2,
            'mode_name'      => 'LOAD FACTOR',
            'tiers'          => [
                'high_tier'   => ['label' => 'High Demand (≥ 85%)', 'count' => $high, 'color' => '#10B981'],
                'normal_tier' => ['label' => 'Optimal (70% - 84%)', 'count' => $normal, 'color' => '#3B82F6'],
                'low_tier'    => ['label' => 'Under-utilized (< 70%)', 'count' => $low, 'color' => '#F59E0B'],
                'zero_cap'    => ['label' => 'Unassigned Cap', 'count' => $zeroCap, 'color' => '#94A3B8'],
            ],
        ];
    }

    /**
     * Mode 3: COMPARE LOAD FACTOR (Period vs period comparison)
     */
    protected function buildMode3ComparePeriod(array $records): array
    {
        $dates = [];
        foreach ($records as $r) {
            if (!empty($r['flight_date']) && $r['flight_date'] !== 'N/A') {
                $dates[] = $r['flight_date'];
            }
        }
        $dates = array_unique($dates);
        sort($dates);

        $mid = max(1, (int)floor(count($dates) / 2));
        $period1Dates = array_slice($dates, 0, $mid);
        $period2Dates = array_slice($dates, $mid);

        $p1Cap = 0; $p1Load = 0; $p1Flights = 0;
        $p2Cap = 0; $p2Load = 0; $p2Flights = 0;

        foreach ($records as $r) {
            $d = $r['flight_date'] ?? '';
            $cap = (int)($r['cap'] ?? 0);
            $load = (int)($r['load'] ?? 0);

            if (in_array($d, $period1Dates)) {
                $p1Flights++;
                $p1Cap += $cap;
                $p1Load += $load;
            } else {
                $p2Flights++;
                $p2Cap += $cap;
                $p2Load += $load;
            }
        }

        $p1Lf = ($p1Cap > 0) ? round(($p1Load / $p1Cap) * 100, 1) : null;
        $p2Lf = ($p2Cap > 0) ? round(($p2Load / $p2Cap) * 100, 1) : null;
        $deltaLf = ($p1Lf !== null && $p2Lf !== null) ? round($p2Lf - $p1Lf, 1) : 0.0;

        return [
            'mode'           => 3,
            'mode_name'      => 'COMPARE LOAD FACTOR',
            'period_1'       => [
                'label'       => (!empty($period1Dates)) ? (reset($period1Dates) . ' → ' . end($period1Dates)) : 'Period 1',
                'flights'     => $p1Flights,
                'capacity'    => $p1Cap,
                'load'        => $p1Load,
                'load_factor' => ($p1Lf !== null) ? "{$p1Lf}%" : 'N/A',
                'lf_num'      => $p1Lf,
            ],
            'period_2'       => [
                'label'       => (!empty($period2Dates)) ? (reset($period2Dates) . ' → ' . end($period2Dates)) : 'Period 2',
                'flights'     => $p2Flights,
                'capacity'    => $p2Cap,
                'load'        => $p2Load,
                'load_factor' => ($p2Lf !== null) ? "{$p2Lf}%" : 'N/A',
                'lf_num'      => $p2Lf,
            ],
            'delta_load_factor' => "{$deltaLf}%",
            'trend'             => ($deltaLf >= 0) ? 'UP' : 'DOWN',
        ];
    }

    /**
     * Mode 4: COMPARE LOAD FACTOR DAY (Day-of-week comparison)
     */
    protected function buildMode4CompareDayOfWeek(array $records): array
    {
        $days = [
            1 => ['name' => 'Monday', 'short' => 'Mon', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            2 => ['name' => 'Tuesday', 'short' => 'Tue', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            3 => ['name' => 'Wednesday', 'short' => 'Wed', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            4 => ['name' => 'Thursday', 'short' => 'Thu', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            5 => ['name' => 'Friday', 'short' => 'Fri', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            6 => ['name' => 'Saturday', 'short' => 'Sat', 'flights' => 0, 'capacity' => 0, 'load' => 0],
            7 => ['name' => 'Sunday', 'short' => 'Sun', 'flights' => 0, 'capacity' => 0, 'load' => 0],
        ];

        foreach ($records as $r) {
            $dStr = $r['flight_date'] ?? null;
            if ($dStr && $dStr !== 'N/A') {
                $dow = (int)date('N', strtotime($dStr));
                if (isset($days[$dow])) {
                    $days[$dow]['flights']++;
                    $days[$dow]['capacity'] += (int)($r['cap'] ?? 0);
                    $days[$dow]['load'] += (int)($r['load'] ?? 0);
                }
            }
        }

        foreach ($days as &$d) {
            $cap = $d['capacity'];
            $load = $d['load'];
            $d['load_factor'] = ($cap > 0) ? round(($load / $cap) * 100, 1) . '%' : 'N/A';
            $d['lf_num'] = ($cap > 0) ? round(($load / $cap) * 100, 1) : 0;
        }
        unset($d);

        return [
            'mode'      => 4,
            'mode_name' => 'COMPARE LOAD FACTOR DAY',
            'days'      => array_values($days),
        ];
    }

    /**
     * Flight / Passenger / Cargo Trend (Combination Chart).
    /**
     * Flight / Passenger / Cargo Trend (Combination Analytics).
     * Synchronized series for:
     * 1. Flight Movement (Grouped Bars: Arr Dom, Arr Int, Dep Dom, Dep Int)
     * 2. Passenger Trend (Line: Arrival Pax & Departure Pax, Total Pax)
     * 3. Cargo Trend (Line/Area: Arrival Cargo & Departure Cargo, Total Cargo kg/Ton)
     * Granularity: Daily if multiple dates (Monthly/Custom), Hourly (00:00-23:00) if Daily/Single Date, Monthly if Yearly.
     */
    public function computeCombinedTrend(array $records, array $options = []): array
    {
        $timeBasis = $options['time_basis'] ?? 'scheduled';
        $analysisLevel = strtoupper($options['analysis_level'] ?? 'DAILY');

        // Check date span
        $dates = [];
        foreach ($records as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
            if ($d && $d !== 'N/A') {
                $dates[$d] = true;
            }
        }
        ksort($dates);
        $dateKeys = array_keys($dates);

        $labels = [];
        $fullDates = [];
        $arrDomFlights = [];
        $arrIntFlights = [];
        $depDomFlights = [];
        $depIntFlights = [];
        $arrivals = [];
        $departures = [];
        $totalFlights = [];

        $arrPassengers = [];
        $depPassengers = [];
        $passengers = [];

        $arrCargoKg = [];
        $depCargoKg = [];
        $cargoKg = [];
        $cargoTon = [];
        $arrCargoTon = [];
        $depCargoTon = [];

        $arrBaggageKg = [];
        $depBaggageKg = [];
        $baggageKg = [];

        $dateScope = strtoupper(trim($options['date_scope'] ?? ''));
        if (empty($dateScope)) {
            if ($analysisLevel === 'FULL' || $analysisLevel === 'ALL') {
                $dateScope = 'ALL_PERIOD';
            } elseif ($analysisLevel === 'DAILY') {
                $dateScope = 'DAY';
            } else {
                $dateScope = (count($dateKeys) > 1) ? 'ALL_PERIOD' : 'DAY';
            }
        }

        $granularity = 'daily';

        if ($analysisLevel === 'YEARLY' && count($dateKeys) > 31) {
            // Yearly granularity: 12 months (Jan to Dec)
            $granularity = 'monthly';
            $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $monthMap = [];
            for ($m = 1; $m <= 12; $m++) {
                $mKey = str_pad($m, 2, '0', STR_PAD_LEFT);
                $monthMap[$mKey] = [
                    'label' => $monthNames[$m - 1],
                    'full_date' => $monthNames[$m - 1],
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
                $mKey = substr($d, 5, 2);
                if (!isset($monthMap[$mKey])) continue;

                $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC');

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) { $pax = (int)($r['load'] ?? ($r['pax_total'] ?? 0)); }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $monthMap[$mKey]['arr_dom']++;
                        $monthMap[$mKey]['arr_dom_pax'] += $pax;
                        $monthMap[$mKey]['arr_dom_cargo'] += $cKg;
                    } else {
                        $monthMap[$mKey]['arr_int']++;
                        $monthMap[$mKey]['arr_int_pax'] += $pax;
                        $monthMap[$mKey]['arr_int_cargo'] += $cKg;
                    }
                    $monthMap[$mKey]['arr_pax'] += $pax;
                    $monthMap[$mKey]['arr_cargo'] += $cKg;
                    $monthMap[$mKey]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $monthMap[$mKey]['dep_dom']++;
                        $monthMap[$mKey]['dep_dom_pax'] += $pax;
                        $monthMap[$mKey]['dep_dom_cargo'] += $cKg;
                    } else {
                        $monthMap[$mKey]['dep_int']++;
                        $monthMap[$mKey]['dep_int_pax'] += $pax;
                        $monthMap[$mKey]['dep_int_cargo'] += $cKg;
                    }
                    $monthMap[$mKey]['dep_pax'] += $pax;
                    $monthMap[$mKey]['dep_cargo'] += $cKg;
                    $monthMap[$mKey]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            foreach ($monthMap as $mKey => $val) {
                $labels[] = $val['label'];
                $fullDates[] = $val['full_date'];
                $arrDomFlights[] = $val['arr_dom'];
                $arrIntFlights[] = $val['arr_int'];
                $depDomFlights[] = $val['dep_dom'];
                $depIntFlights[] = $val['dep_int'];
                $arrTot = $val['arr_dom'] + $val['arr_int'];
                $depTot = $val['dep_dom'] + $val['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $val['arr_pax'];
                $depPassengers[] = $val['dep_pax'];
                $passengers[] = $val['arr_pax'] + $val['dep_pax'];
                $arrDomPax[] = $val['arr_dom_pax'];
                $arrIntPax[] = $val['arr_int_pax'];
                $depDomPax[] = $val['dep_dom_pax'];
                $depIntPax[] = $val['dep_int_pax'];

                $arrCargoKg[] = round($val['arr_cargo'], 1);
                $depCargoKg[] = round($val['dep_cargo'], 1);
                $totC = $val['arr_cargo'] + $val['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($val['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($val['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($val['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($val['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($val['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($val['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($val['arr_bagg'], 1);
                $depBaggageKg[] = round($val['dep_bagg'], 1);
                $baggageKg[] = round($val['arr_bagg'] + $val['dep_bagg'], 1);
            }
        } elseif ($dateScope === 'ALL_PERIOD' || ($dateScope !== 'DAY' && count($dateKeys) > 1)) {
            // Multi-day / Full-Range granularity: Daily timeline
            $granularity = 'daily';
            $dailyMap = [];
            foreach ($dateKeys as $dk) {
                $dailyMap[$dk] = [
                    'label' => date('d M', strtotime($dk)),
                    'full_date' => date('d M Y', strtotime($dk)),
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
                $stdD = FlightDailyReportFilter::standardizeDate($d);
                if (!isset($dailyMap[$stdD])) {
                    if (isset($dailyMap[$d])) {
                        $stdD = $d;
                    } else {
                        continue;
                    }
                }

                $isArr = ($r['direction'] ?? '') === 'ARRIVAL';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? 'DOMESTIC')));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC');

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) { $pax = (int)($r['load'] ?? ($r['pax_total'] ?? 0)); }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $dailyMap[$stdD]['arr_dom']++;
                        $dailyMap[$stdD]['arr_dom_pax'] += $pax;
                        $dailyMap[$stdD]['arr_dom_cargo'] += $cKg;
                    } else {
                        $dailyMap[$stdD]['arr_int']++;
                        $dailyMap[$stdD]['arr_int_pax'] += $pax;
                        $dailyMap[$stdD]['arr_int_cargo'] += $cKg;
                    }
                    $dailyMap[$stdD]['arr_pax'] += $pax;
                    $dailyMap[$stdD]['arr_cargo'] += $cKg;
                    $dailyMap[$stdD]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $dailyMap[$stdD]['dep_dom']++;
                        $dailyMap[$stdD]['dep_dom_pax'] += $pax;
                        $dailyMap[$stdD]['dep_dom_cargo'] += $cKg;
                    } else {
                        $dailyMap[$stdD]['dep_int']++;
                        $dailyMap[$stdD]['dep_int_pax'] += $pax;
                        $dailyMap[$stdD]['dep_int_cargo'] += $cKg;
                    }
                    $dailyMap[$stdD]['dep_pax'] += $pax;
                    $dailyMap[$stdD]['dep_cargo'] += $cKg;
                    $dailyMap[$stdD]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            foreach ($dailyMap as $dk => $val) {
                $labels[] = $val['label'];
                $fullDates[] = $val['full_date'];
                $arrDomFlights[] = $val['arr_dom'];
                $arrIntFlights[] = $val['arr_int'];
                $depDomFlights[] = $val['dep_dom'];
                $depIntFlights[] = $val['dep_int'];
                $arrTot = $val['arr_dom'] + $val['arr_int'];
                $depTot = $val['dep_dom'] + $val['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $val['arr_pax'];
                $depPassengers[] = $val['dep_pax'];
                $passengers[] = $val['arr_pax'] + $val['dep_pax'];
                $arrDomPax[] = $val['arr_dom_pax'];
                $arrIntPax[] = $val['arr_int_pax'];
                $depDomPax[] = $val['dep_dom_pax'];
                $depIntPax[] = $val['dep_int_pax'];

                $arrCargoKg[] = round($val['arr_cargo'], 1);
                $depCargoKg[] = round($val['dep_cargo'], 1);
                $totC = $val['arr_cargo'] + $val['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($val['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($val['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($val['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($val['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($val['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($val['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($val['arr_bagg'], 1);
                $depBaggageKg[] = round($val['dep_bagg'], 1);
                $baggageKg[] = round($val['arr_bagg'] + $val['dep_bagg'], 1);
            }
        } else {
            // Single-day -> Hourly granularity strictly 24 hours (00:00 to 23:00)
            $granularity = 'hourly';
            $hourlyMap = [];
            for ($h = 0; $h < 24; $h++) {
                $lbl = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
                $range = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00–' . str_pad($h, 2, '0', STR_PAD_LEFT) . ':59';
                $hourlyMap[$h] = [
                    'label' => $lbl,
                    'full_date' => $range,
                    'arr_dom' => 0, 'arr_int' => 0, 'dep_dom' => 0, 'dep_int' => 0,
                    'arr_pax' => 0, 'dep_pax' => 0,
                    'arr_dom_pax' => 0, 'arr_int_pax' => 0, 'dep_dom_pax' => 0, 'dep_int_pax' => 0,
                    'arr_cargo' => 0.0, 'dep_cargo' => 0.0,
                    'arr_dom_cargo' => 0.0, 'arr_int_cargo' => 0.0, 'dep_dom_cargo' => 0.0, 'dep_int_cargo' => 0.0,
                    'arr_bagg' => 0.0, 'dep_bagg' => 0.0,
                ];
            }

            foreach ($records as $r) {
                $isArr = ($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR';
                $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? ($r['dom_int'] ?? 'DOMESTIC'))));
                $isDom = ($tr === 'DOM' || $tr === 'DOMESTIC' || $tr === 'D');

                $ts = '';
                if ($timeBasis === 'actual') {
                    $ts = $isArr ? ($r['aibt'] ?? ($r['sibt'] ?? '')) : ($r['aobt'] ?? ($r['sobt'] ?? ''));
                } else {
                    $ts = $isArr ? ($r['sibt'] ?? ($r['aibt'] ?? '')) : ($r['sobt'] ?? ($r['aobt'] ?? ''));
                }

                $h = 0;
                if ($ts && $ts !== 'N/A' && preg_match('/(\d{1,2}):(\d{2})/', $ts, $m)) {
                    $h = (int)$m[1];
                }
                if ($h < 0 || $h > 23) $h = 0;

                $pax = (int)($r['adult'] ?? 0) + (int)($r['child'] ?? 0) + (int)($r['infant'] ?? 0);
                if ($pax === 0) {
                    $pax = (int)($r['load'] ?? ($r['pax_total'] ?? ($r['total_passenger'] ?? 0)));
                }

                $cKg = (float)($r['cargo_kg'] ?? 0.0);
                $bKg = (float)($r['baggage_kg'] ?? 0.0);

                if ($isArr) {
                    if ($isDom) {
                        $hourlyMap[$h]['arr_dom']++;
                        $hourlyMap[$h]['arr_dom_pax'] += $pax;
                        $hourlyMap[$h]['arr_dom_cargo'] += $cKg;
                    } else {
                        $hourlyMap[$h]['arr_int']++;
                        $hourlyMap[$h]['arr_int_pax'] += $pax;
                        $hourlyMap[$h]['arr_int_cargo'] += $cKg;
                    }
                    $hourlyMap[$h]['arr_pax'] += $pax;
                    $hourlyMap[$h]['arr_cargo'] += $cKg;
                    $hourlyMap[$h]['arr_bagg'] += $bKg;
                } else {
                    if ($isDom) {
                        $hourlyMap[$h]['dep_dom']++;
                        $hourlyMap[$h]['dep_dom_pax'] += $pax;
                        $hourlyMap[$h]['dep_dom_cargo'] += $cKg;
                    } else {
                        $hourlyMap[$h]['dep_int']++;
                        $hourlyMap[$h]['dep_int_pax'] += $pax;
                        $hourlyMap[$h]['dep_int_cargo'] += $cKg;
                    }
                    $hourlyMap[$h]['dep_pax'] += $pax;
                    $hourlyMap[$h]['dep_cargo'] += $cKg;
                    $hourlyMap[$h]['dep_bagg'] += $bKg;
                }
            }

            $arrDomPax = [];
            $arrIntPax = [];
            $depDomPax = [];
            $depIntPax = [];
            $arrDomCargoKg = [];
            $arrIntCargoKg = [];
            $depDomCargoKg = [];
            $depIntCargoKg = [];

            for ($h = 0; $h < 24; $h++) {
                $labels[] = $hourlyMap[$h]['label'];
                $fullDates[] = $hourlyMap[$h]['full_date'];
                $arrDomFlights[] = $hourlyMap[$h]['arr_dom'];
                $arrIntFlights[] = $hourlyMap[$h]['arr_int'];
                $depDomFlights[] = $hourlyMap[$h]['dep_dom'];
                $depIntFlights[] = $hourlyMap[$h]['dep_int'];
                $arrTot = $hourlyMap[$h]['arr_dom'] + $hourlyMap[$h]['arr_int'];
                $depTot = $hourlyMap[$h]['dep_dom'] + $hourlyMap[$h]['dep_int'];
                $arrivals[] = $arrTot;
                $departures[] = $depTot;
                $totalFlights[] = $arrTot + $depTot;

                $arrPassengers[] = $hourlyMap[$h]['arr_pax'];
                $depPassengers[] = $hourlyMap[$h]['dep_pax'];
                $passengers[] = $hourlyMap[$h]['arr_pax'] + $hourlyMap[$h]['dep_pax'];
                $arrDomPax[] = $hourlyMap[$h]['arr_dom_pax'];
                $arrIntPax[] = $hourlyMap[$h]['arr_int_pax'];
                $depDomPax[] = $hourlyMap[$h]['dep_dom_pax'];
                $depIntPax[] = $hourlyMap[$h]['dep_int_pax'];

                $arrCargoKg[] = round($hourlyMap[$h]['arr_cargo'], 1);
                $depCargoKg[] = round($hourlyMap[$h]['dep_cargo'], 1);
                $totC = $hourlyMap[$h]['arr_cargo'] + $hourlyMap[$h]['dep_cargo'];
                $cargoKg[] = round($totC, 1);
                $cargoTon[] = round($totC / 1000, 2);
                $arrCargoTon[] = round($hourlyMap[$h]['arr_cargo'] / 1000, 2);
                $depCargoTon[] = round($hourlyMap[$h]['dep_cargo'] / 1000, 2);
                $arrDomCargoKg[] = round($hourlyMap[$h]['arr_dom_cargo'], 1);
                $arrIntCargoKg[] = round($hourlyMap[$h]['arr_int_cargo'], 1);
                $depDomCargoKg[] = round($hourlyMap[$h]['dep_dom_cargo'], 1);
                $depIntCargoKg[] = round($hourlyMap[$h]['dep_int_cargo'], 1);

                $arrBaggageKg[] = round($hourlyMap[$h]['arr_bagg'], 1);
                $depBaggageKg[] = round($hourlyMap[$h]['dep_bagg'], 1);
                $baggageKg[] = round($hourlyMap[$h]['arr_bagg'] + $hourlyMap[$h]['dep_bagg'], 1);
            }
        }

        return [
            'granularity'          => $granularity,
            'labels'               => $labels,
            'full_dates'           => $fullDates,
            'time_ranges'          => $fullDates,
            'arr_dom_flights'      => $arrDomFlights,
            'arr_int_flights'      => $arrIntFlights,
            'dep_dom_flights'      => $depDomFlights,
            'dep_int_flights'      => $depIntFlights,
            'arrivals'             => $arrivals,
            'departures'           => $departures,
            'total_flights'        => $totalFlights,
            'arr_passengers'       => $arrPassengers,
            'dep_passengers'       => $depPassengers,
            'arr_dom_passengers'   => $arrDomPax,
            'arr_int_passengers'   => $arrIntPax,
            'dep_dom_passengers'   => $depDomPax,
            'dep_int_passengers'   => $depIntPax,
            'passengers'           => $passengers,
            'arr_cargo_kg'         => $arrCargoKg,
            'dep_cargo_kg'         => $depCargoKg,
            'arr_dom_cargo_kg'     => $arrDomCargoKg,
            'arr_int_cargo_kg'     => $arrIntCargoKg,
            'dep_dom_cargo_kg'     => $depDomCargoKg,
            'dep_int_cargo_kg'     => $depIntCargoKg,
            'cargo_kg'             => $cargoKg,
            'cargo_ton'            => $cargoTon,
            'arr_cargo_ton'        => $arrCargoTon,
            'dep_cargo_ton'        => $depCargoTon,
            'arr_baggage_kg'       => $arrBaggageKg,
            'dep_baggage_kg'       => $depBaggageKg,
            'baggage_kg'           => $baggageKg,
            'total_arrivals'       => array_sum($arrivals),
            'total_departures'     => array_sum($departures),
            'total_flights_count'  => array_sum($totalFlights),
            'total_passengers'     => array_sum($passengers),
            'total_arr_passengers' => array_sum($arrPassengers),
            'total_dep_passengers' => array_sum($depPassengers),
            'total_cargo_kg'       => round(array_sum($cargoKg), 1),
            'total_cargo_ton'      => round(array_sum($cargoTon), 2),
            'total_baggage_kg'     => round(array_sum($baggageKg), 1),
            'series'               => [
                'arrival'   => $arrivals,
                'departure' => $departures,
                'flights'   => $totalFlights,
                'passenger' => $passengers,
                'cargo'     => $cargoKg,
            ],
        ];
    }

    /**
     * FDR Hourly Movement Distribution strictly covering 24 hourly buckets (00:00 to 23:00).
     * Period-wide aggregation across the selected source period (single-day, monthly, yearly, full-range).
     * Conceptually aligned with hourly distribution, without any capacity constraints or envelopes.
     *
     * @param array $records Filtered operational records
     * @param array $options Options including 'time_basis', 'date_scope', 'analysis_date', etc.
     * @param array $meta Upload metadata (for period dates)
     * @return array
     */
    public function computeHourlyMovementDistribution(array $records, array $options = [], array $meta = []): array
    {
        $timeBasis = in_array(strtolower($options['time_basis'] ?? 'actual'), ['scheduled', 'actual'], true)
            ? strtolower($options['time_basis'] ?? 'actual')
            : 'actual';

        // 1. Gather all distinct dates and date span across records
        $dates = [];
        foreach ($records as $r) {
            $d = $r['operational_date'] ?? ($r['flight_date'] ?? '');
            if ($d && $d !== 'N/A') {
                $dates[$d] = true;
            }
        }
        $distinctDaysCount = count($dates);

        $dateScope = strtoupper(trim($options['date_scope'] ?? ''));
        $analysisDate = trim($options['analysis_date'] ?? '');

        if ($dateScope === 'DAY' && !empty($analysisDate)) {
            $periodDays = 1;
            $periodLabel = date('d M Y', strtotime($analysisDate));
        } else {
            $start = $meta['period_start'] ?? (!empty($dates) ? min(array_keys($dates)) : null);
            $end = $meta['period_end'] ?? (!empty($dates) ? max(array_keys($dates)) : null);

            if ($start && $end) {
                try {
                    $periodDays = max(1, (int)Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1);
                    $periodLabel = strtoupper(date('d M Y', strtotime($start))) . ' → ' . strtoupper(date('d M Y', strtotime($end)));
                } catch (\Exception $e) {
                    $periodDays = max(1, $distinctDaysCount);
                    $periodLabel = 'SELECTED PERIOD';
                }
            } else {
                $periodDays = max(1, $distinctDaysCount);
                $periodLabel = 'SELECTED PERIOD';
            }
        }

        $avgDenominator = max(1, $periodDays);

        // 2. Initialize exactly 24 hourly buckets (00:00 to 23:00)
        $buckets = [];
        $labels = [];
        $timeRanges = [];
        for ($h = 0; $h < 24; $h++) {
            $hStr = str_pad((string)$h, 2, '0', STR_PAD_LEFT);
            $labels[] = "{$hStr}:00";
            $timeRanges[] = "{$hStr}:00–{$hStr}:59";
            $buckets[$h] = [
                'hour'            => $h,
                'label'           => "{$hStr}:00",
                'time_range'      => "{$hStr}:00–{$hStr}:59",
                'arr_dom'         => 0,
                'arr_int'         => 0,
                'dep_dom'         => 0,
                'dep_int'         => 0,
                'arr_total'       => 0,
                'dep_total'       => 0,
                'total'           => 0,
                'average_per_day' => 0.0,
            ];
        }

        $validArrEvaluated = 0;
        $validDepEvaluated = 0;

        // 3. Process each valid movement record into its hour bucket
        foreach ($records as $r) {
            // Guardrail: skip summary rows and PAX ALL
            if (($r['row_type'] ?? '') === 'SUMMARY') continue;
            $al = trim($r['air_line'] ?? ($r['operator'] ?? ''));
            if (strcasecmp($al, 'PAX ALL') === 0 || stripos($al, 'PAX ALL') !== false) continue;

            $isArr = (($r['direction'] ?? '') === 'ARRIVAL' || ($r['movement_type'] ?? '') === 'A' || ($r['flow'] ?? '') === 'ARR');
            $tr = strtoupper(trim($r['traffic'] ?? ($r['route_type'] ?? ($r['dom_int'] ?? 'DOMESTIC'))));
            $isDom = in_array($tr, ['DOM', 'DOMESTIC', 'D'], true);

            $hour = null;

            if ($timeBasis === 'actual') {
                // Section 8 & 43: Actual mode uses AIBT for Arrival, AOBT for Departure
                $ts = $isArr ? ($r['aibt'] ?? ($r['arr_actual'] ?? null)) : ($r['aobt'] ?? ($r['dep_actual'] ?? null));
                if (!empty($ts) && $ts !== 'N/A' && preg_match('/(?:^|\s|T)(\d{1,2}):(\d{2})/', (string)$ts, $m)) {
                    $hour = (int)$m[1];
                }
            } else {
                // Section 7 & 43: Scheduled mode uses SIBT for Arrival, SOBT for Departure
                $ts = $isArr ? ($r['sibt'] ?? ($r['arr_sched'] ?? null)) : ($r['sobt'] ?? ($r['dep_sched'] ?? null));
                if (!empty($ts) && $ts !== 'N/A' && preg_match('/(?:^|\s|T)(\d{1,2}):(\d{2})/', (string)$ts, $m)) {
                    $hour = (int)$m[1];
                } elseif (isset($r['hour']) && is_numeric($r['hour'])) {
                    $hour = (int)$r['hour'];
                }
            }

            if ($hour === null || $hour < 0 || $hour > 23) {
                continue;
            }

            if ($isArr) {
                $validArrEvaluated++;
                if ($isDom) {
                    $buckets[$hour]['arr_dom']++;
                } else {
                    $buckets[$hour]['arr_int']++;
                }
                $buckets[$hour]['arr_total']++;
            } else {
                $validDepEvaluated++;
                if ($isDom) {
                    $buckets[$hour]['dep_dom']++;
                } else {
                    $buckets[$hour]['dep_int']++;
                }
                $buckets[$hour]['dep_total']++;
            }

            $buckets[$hour]['total']++;
        }

        // 4. Calculate average per day for each hour
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h]['average_per_day'] = round($buckets[$h]['total'] / $avgDenominator, 2);
        }

        // 5. Build series arrays
        $arrDom = array_column($buckets, 'arr_dom');
        $arrInt = array_column($buckets, 'arr_int');
        $depDom = array_column($buckets, 'dep_dom');
        $depInt = array_column($buckets, 'dep_int');
        $arrTotal = array_column($buckets, 'arr_total');
        $depTotal = array_column($buckets, 'dep_total');
        $total = array_column($buckets, 'total');
        $avgPerDay = array_column($buckets, 'average_per_day');

        // 6. Compute Peak Hour KPI (Section 25)
        $maxMovements = max(0, ...$total);
        $peakHourObj = null;

        if ($maxMovements > 0) {
            $peakH = null;
            for ($h = 0; $h < 24; $h++) {
                if ($buckets[$h]['total'] === $maxMovements) {
                    $peakH = $h;
                    break;
                }
            }
            $peakHourRange = $buckets[$peakH]['time_range'];
            $peakAvg = $buckets[$peakH]['average_per_day'];
            $peakDisplay = "{$peakHourRange} with " . number_format($maxMovements) . " movements";

            $peakHourObj = [
                'hour'            => $peakH,
                'time_range'      => $peakHourRange,
                'movements'       => $maxMovements,
                'average_per_day' => $peakAvg,
                'display'         => $peakDisplay,
            ];
        } else {
            $peakHourObj = [
                'hour'            => null,
                'time_range'      => 'N/A',
                'movements'       => 0,
                'average_per_day' => 0.0,
                'display'         => 'N/A',
            ];
        }

        return [
            'labels'             => $labels,
            'time_ranges'        => $timeRanges,
            'hours'              => range(0, 23),
            'time_basis'         => $timeBasis,
            'time_basis_desc'    => ($timeBasis === 'actual') ? 'AIBT / AOBT' : 'SIBT / SOBT',
            'period_label'       => $periodLabel,
            'period_days'        => $periodDays,
            'available_days'     => $distinctDaysCount,
            'arr_dom'            => $arrDom,
            'arr_int'            => $arrInt,
            'dep_dom'            => $depDom,
            'dep_int'            => $depInt,
            'arr_total'          => $arrTotal,
            'dep_total'          => $depTotal,
            'total'              => $total,
            'average_per_day'    => $avgPerDay,
            'buckets'            => array_values($buckets),
            'peak_hour'          => $peakHourObj,
            'valid_arr_count'    => $validArrEvaluated,
            'valid_dep_count'    => $validDepEvaluated,
            'total_evaluated'    => array_sum($total),
        ];
    }
}

