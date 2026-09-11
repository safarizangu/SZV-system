<?php
/**
 * Safari Zangu — Seed Complete Itineraries & Quotations
 * Run: php8.3 seed_itineraries_quotations.php
 * This builds proper day-by-day itineraries for all 6 packages
 * and creates a sample quotation for each.
 */

require_once __DIR__ . '/app/bootstrap.php';

$db = App\Models\Database::getInstance();

echo "═══════════════════════════════════════════════════\n";
echo "  SZV — SEED ITINERARIES & QUOTATIONS\n";
echo "═══════════════════════════════════════════════════\n\n";

// ── Helper ────────────────────────────────────────────────────
function upsertItinerary($db, $data) {
    $existing = $db->prepare("SELECT id FROM itineraries WHERE slug=? LIMIT 1");
    $existing->execute([$data['slug']]);
    $row = $existing->fetch();

    $cols = array_keys($data);
    if ($row) {
        $sets = implode(',', array_map(fn($k) => "`$k`=?", $cols));
        $db->prepare("UPDATE itineraries SET $sets WHERE slug=?")
           ->execute([...array_values($data), $data['slug']]);
        echo "  UPDATED itinerary: {$data['name']}\n";
        return $row['id'];
    } else {
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colStr = implode(',', array_map(fn($k) => "`$k`", $cols));
        $db->prepare("INSERT INTO itineraries ($colStr) VALUES ($placeholders)")
           ->execute(array_values($data));
        echo "  CREATED itinerary: {$data['name']}\n";
        return (int)$db->lastInsertId();
    }
}

function linkPackage($db, $pkgSlug, $itinId) {
    $db->prepare("UPDATE packages SET itinerary_id=? WHERE slug=?")
       ->execute([$itinId, $pkgSlug]);
    echo "  Linked package '$pkgSlug' → itinerary $itinId\n";
}

function makeQuotation($db, $pkgName, $itinId, $adults, $durationDays, $lineItems, $accLevel='midrange') {
    $subtotal  = array_sum(array_column($lineItems, 'amount'));
    $discount  = 0;
    $afterDisc = $subtotal;
    $vat       = round($afterDisc * 0.18, 2);
    $grand     = round($afterDisc + $vat, 2);
    $perPerson = round($grand / $adults, 2);
    $deposit   = round($grand * 0.30, 2);
    $balance   = round($grand - $deposit, 2);
    $ref       = 'QUO-SZV-' . strtoupper(substr(md5($pkgName.time()), 0, 6));
    $valid     = date('Y-m-d', strtotime('+14 days'));

    $existing = $db->prepare("SELECT id FROM quotations WHERE package_name=? AND status='draft' LIMIT 1");
    $existing->execute([$pkgName]);
    if ($existing->fetch()) {
        echo "  Quotation for '$pkgName' already exists — skipping\n";
        return;
    }

    $db->prepare("INSERT INTO quotations
        (quote_ref, client_name, client_email, client_phone, client_country,
         package_name, itinerary_id, start_date, duration_days, adults, children,
         accommodation_level, line_items, discount_pct,
         subtotal, vat, grand_total, per_person, deposit_amount, balance_due,
         valid_until, status, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
        ->execute([
            $ref,
            'Sample Guest', 'guest@example.com', '+44 7700 900000', 'United Kingdom',
            $pkgName, $itinId,
            date('Y-m-d', strtotime('+90 days')),
            $durationDays, $adults, 0,
            $accLevel,
            json_encode($lineItems),
            $discount,
            $subtotal, $vat, $grand, $perPerson, $deposit, $balance,
            $valid, 'draft'
        ]);
    echo "  CREATED quotation: $ref for '$pkgName' — Grand: \${$grand}\n";
}

// ══════════════════════════════════════════════════════════════
// 1. CLASSIC SAFARI — 5 Nights (Tarangire + Serengeti + Ngorongoro)
// ══════════════════════════════════════════════════════════════
echo "\n[1] Classic Safari — 5 Nights\n";

$classicDays = json_encode([
    [
        'day' => 1, 'title' => 'Arusha → Tarangire National Park',
        'description' => 'Depart Arusha after breakfast. Drive south through the Maasai steppe to Tarangire — famous for its giant baobab trees and one of Africa\'s highest concentrations of elephants. Afternoon game drive along the Tarangire River where hippos wallow and crocodiles bask. Watch thousands of zebra and wildebeest as predators stalk the riverbanks.',
        'location' => 'Tarangire NP',
        'accommodation_name' => 'Tarangire Safari Lodge', 'accommodation_rate' => 180, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Tarangire NP', 'park_adult_fee' => 59,
        'meals_cost_pp' => 45,
        'activities' => [['name'=>'Afternoon game drive','type'=>'included'],['name'=>'Sundowner at the river','type'=>'included']],
        'other_items' => []
    ],
    [
        'day' => 2, 'title' => 'Full Day Tarangire — Elephant Country',
        'description' => 'A full day in Tarangire — one of Tanzania\'s most underrated gems. Morning game drive through ancient baobab forest tracking large elephant herds, lion prides, and cheetah. Packed lunch in the bush. Afternoon explore the silale swamps where buffalo herds of 1,000+ gather. Watch the dramatic light at golden hour over the river.',
        'location' => 'Tarangire NP',
        'accommodation_name' => 'Tarangire Safari Lodge', 'accommodation_rate' => 180, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Tarangire NP', 'park_adult_fee' => 59,
        'meals_cost_pp' => 50,
        'activities' => [['name'=>'Morning game drive','type'=>'included'],['name'=>'Bush lunch','type'=>'included'],['name'=>'Afternoon game drive','type'=>'included']],
        'other_items' => []
    ],
    [
        'day' => 3, 'title' => 'Tarangire → Central Serengeti',
        'description' => 'Early morning game drive before breakfast. Drive northwest through the Ngorongoro Conservation Area via Karatu and the Ngorongoro Crater rim. Descend into the Serengeti ecosystem, stopping at the entrance gate before exploring the Seronera Valley — the wildlife heart of the Serengeti where the Big Five roam year-round. Late afternoon arrival at camp.',
        'location' => 'Central Serengeti',
        'accommodation_name' => 'Serengeti Serena Safari Lodge', 'accommodation_rate' => 220, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Serengeti NP', 'park_adult_fee' => 82,
        'meals_cost_pp' => 50,
        'activities' => [['name'=>'En-route game drives','type'=>'included'],['name'=>'Seronera Valley drive','type'=>'included']],
        'other_items' => []
    ],
    [
        'day' => 4, 'title' => 'Full Day Serengeti — Big Five Territory',
        'description' => 'Dawn game drive from camp as lions return from night hunts. The Seronera Valley is home to the highest density of leopards in Africa — your guide knows the favourite trees. Morning drive through kopje-dotted plains where cheetahs use termite mounds as vantage points. Picnic lunch at a shaded kopje. Afternoon following the wildebeest herds and watching hippos in the Seronera River pools.',
        'location' => 'Central Serengeti',
        'accommodation_name' => 'Serengeti Serena Safari Lodge', 'accommodation_rate' => 220, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Serengeti NP', 'park_adult_fee' => 82,
        'meals_cost_pp' => 50,
        'activities' => [['name'=>'Dawn game drive','type'=>'included'],['name'=>'Leopard tracking','type'=>'included'],['name'=>'Kopje picnic lunch','type'=>'included'],['name'=>'Afternoon game drive','type'=>'included']],
        'other_items' => []
    ],
    [
        'day' => 5, 'title' => 'Serengeti → Ngorongoro Crater',
        'description' => 'Final morning game drive in the Serengeti before driving east to Ngorongoro. Arrive at the crater rim as the afternoon mist rolls in — the view from 2,200 metres into the world\'s largest intact volcanic caldera is breathtaking. Early dinner and a chance to spot hyenas at the crater rim as darkness falls.',
        'location' => 'Ngorongoro',
        'accommodation_name' => 'Ngorongoro Serena Safari Lodge', 'accommodation_rate' => 230, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Ngorongoro CA', 'park_adult_fee' => 80,
        'meals_cost_pp' => 55,
        'activities' => [['name'=>'Serengeti morning drive','type'=>'included'],['name'=>'Crater rim evening walk','type'=>'included']],
        'other_items' => []
    ],
    [
        'day' => 6, 'title' => 'Ngorongoro Crater Full Day → Arusha',
        'description' => 'Descend 600 metres into the Ngorongoro Crater floor — a UNESCO World Heritage Site and the \'Garden of Eden\'. The crater floor hosts 25,000 large animals in a 264 sq km bowl: lions, black rhino (rare sighting), elephant, hippo, flamingo-pink lakes, and enormous buffalo herds. No animal can leave — this is pure predator-prey drama. Return to Arusha in the afternoon.',
        'location' => 'Ngorongoro / Arusha',
        'accommodation_name' => '', 'accommodation_rate' => 0, 'accommodation_rooms' => 1,
        'vehicle_name' => 'Toyota Land Cruiser (4WD pop-up roof)', 'vehicle_rate' => 250,
        'park_fee_name' => 'Ngorongoro CA', 'park_adult_fee' => 80,
        'meals_cost_pp' => 40,
        'activities' => [['name'=>'Full crater descent & game drive','type'=>'included'],['name'=>'Picnic lunch in crater','type'=>'included']],
        'other_items' => [['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle']]
    ]
]);

$classicItinId = upsertItinerary($db, [
    'name'              => 'Classic Safari — 5 Nights',
    'slug'              => 'classic-safari-5-nights',
    'duration_days'     => 5,
    'overview'          => 'Tanzania\'s greatest hits — Tarangire elephants, Serengeti Big Five, and Ngorongoro Crater in one seamless circuit. The benchmark East Africa safari experience.',
    'accommodation_level' => 'midrange',
    'days'              => $classicDays,
    'includes'          => json_encode(['All park entry fees (Tarangire, Serengeti, Ngorongoro)','Private 4WD Toyota Land Cruiser with pop-up roof','Professional TANAPA-licensed safari guide','5 nights accommodation (2 Tarangire, 2 Serengeti, 1 Ngorongoro)','All meals on safari (full board)','Unlimited game drives in all parks','Ngorongoro Crater descent','Drinking water in vehicle','Airport/hotel transfers in Arusha','Rescue/emergency support']),
    'excludes'          => json_encode(['International & domestic flights','Tanzania visa fees (USD 50 at airport)','Personal travel insurance (required)','Gratuities for guide & lodge staff','Optional activities (hot air balloon, Maasai village)','Items of personal nature','Alcoholic beverages (unless lodge package includes)']),
    'base_price_pax'    => 1650.00,
    'is_active'         => 1, 'sort_order' => 1
]);
linkPackage($db, 'classic-safari', $classicItinId);

// Classic Safari quotation — 2 adults, midrange
makeQuotation($db, 'Classic Safari', $classicItinId, 2, 5, [
    ['category'=>'accommodation','description'=>'Tarangire Safari Lodge — 2 nights (2 pax)','unit_price'=>180,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>360],
    ['category'=>'accommodation','description'=>'Serengeti Serena Safari Lodge — 2 nights (2 pax)','unit_price'=>220,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>440],
    ['category'=>'accommodation','description'=>'Ngorongoro Serena Safari Lodge — 1 night (2 pax)','unit_price'=>230,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>230],
    ['category'=>'transport','description'=>'Private Toyota Land Cruiser 4WD — 5 days','unit_price'=>250,'quantity_num'=>5,'quantity_str'=>'5 days','amount'=>1250],
    ['category'=>'park_fees','description'=>'Tarangire NP — 2 days × 2 adults × $59','unit_price'=>59,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>236],
    ['category'=>'park_fees','description'=>'Serengeti NP — 2 days × 2 adults × $82','unit_price'=>82,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>328],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 2 days × 2 adults × $80','unit_price'=>80,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>320],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent fee','unit_price'=>70,'quantity_num'=>1,'quantity_str'=>'1 vehicle','amount'=>70],
    ['category'=>'meals','description'=>'Full board meals — 5 days × 2 pax × $48/pp/day','unit_price'=>48,'quantity_num'=>10,'quantity_str'=>'10 person-days','amount'=>480],
    ['category'=>'other','description'=>'Guide gratuity (recommended)','unit_price'=>20,'quantity_num'=>5,'quantity_str'=>'5 days','amount'=>100],
    ['category'=>'other','description'=>'Service & administration','unit_price'=>150,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>150],
], 'midrange');

// ══════════════════════════════════════════════════════════════
// 2. LUXURY SAFARI — 6 Nights
// ══════════════════════════════════════════════════════════════
echo "\n[2] Luxury Safari — 6 Nights\n";

$luxuryDays = json_encode([
    ['day'=>1,'title'=>'Private Charter: Arusha → Serengeti','description'=>'Fly from Arusha\'s Kilimanjaro Airport directly to the Seronera airstrip in the heart of the Serengeti. Your guide meets you on the airstrip — no long road transfer. Afternoon private game drive with a cold champagne toast at sunset over the plains.','location'=>'Central Serengeti','accommodation_name'=>'Four Seasons Safari Lodge Serengeti','accommodation_rate'=>680,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>120,'activities'=>[['name'=>'Private charter flight Arusha→Serengeti','type'=>'included'],['name'=>'Champagne sunset drive','type'=>'included']],'other_items'=>[['name'=>'Charter flight (estimate)','amount'=>600,'per'=>'group']]],
    ['day'=>2,'title'=>'Serengeti — Private Concession Game Drives','description'=>'Exclusive access to a private wildlife concession bordering the Serengeti. No park rules — off-road driving, night game drives, and walking safaris with an armed ranger. Your guide has decades of experience and knows individual lion prides by name. Bush breakfast delivered to a kopje with views across the endless plains.','location'=>'Central Serengeti','accommodation_name'=>'Four Seasons Safari Lodge Serengeti','accommodation_rate'=>680,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>120,'activities'=>[['name'=>'Dawn game drive','type'=>'included'],['name'=>'Bush breakfast','type'=>'included'],['name'=>'Walking safari with ranger','type'=>'included'],['name'=>'Night game drive','type'=>'included']],'other_items'=>[]],
    ['day'=>3,'title'=>'Hot Air Balloon over the Serengeti','description'=>'Pre-dawn wake-up for the experience of a lifetime — a hot air balloon drift over the Serengeti at sunrise. Watch the Great Migration from above as thousands of wildebeest thunder across the plains below. Land in the bush for a champagne breakfast laid out on starched linen. Afternoon at leisure with pool access and spa treatments available.','location'=>'Central Serengeti','accommodation_name'=>'Four Seasons Safari Lodge Serengeti','accommodation_rate'=>680,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>120,'activities'=>[['name'=>'Hot air balloon flight (60–90 min)','type'=>'included'],['name'=>'Champagne bush breakfast','type'=>'included'],['name'=>'Afternoon spa (1 treatment pp)','type'=>'included']],'other_items'=>[['name'=>'Balloon flight fee','amount'=>700,'per'=>'person']]],
    ['day'=>4,'title'=>'Fly to Ngorongoro — Crater Luxury Camp','description'=>'Morning flight to Ngorongoro. Your private guide meets you and escorts you to an exclusive luxury tented camp set on the crater rim with sweeping views across the caldera. Afternoon private crater descent — your vehicle is the only one in your section. Sundowners on the crater rim as the mist rolls in below.','location'=>'Ngorongoro','accommodation_name'=>'&Beyond Ngorongoro Crater Lodge','accommodation_rate'=>920,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>130,'activities'=>[['name'=>'Private crater descent','type'=>'included'],['name'=>'Crater rim sundowners','type'=>'included']],'other_items'=>[['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle']]],
    ['day'=>5,'title'=>'Ngorongoro Crater — Exclusive Full Day','description'=>'Exclusive full-day crater experience. Your private vehicle enters at first light before the tour vehicles arrive. The 264 sq km caldera is yours to explore at your own pace. Special permit for off-road driving. Gourmet bush picnic prepared by your private chef. Real chance of seeing all Big Five plus black rhino — one of Tanzania\'s rarest sightings.','location'=>'Ngorongoro','accommodation_name'=>'&Beyond Ngorongoro Crater Lodge','accommodation_rate'=>920,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>130,'activities'=>[['name'=>'Full crater descent (all day)','type'=>'included'],['name'=>'Gourmet bush picnic','type'=>'included'],['name'=>'Black rhino tracking','type'=>'included']],'other_items'=>[]],
    ['day'=>6,'title'=>'Tarangire — Elephant Paradise → Arusha','description'=>'Fly to Tarangire for a private morning game drive among the largest elephant herds in Tanzania — groups of 200+ are common. The ancient baobab trees are millennia old, their hollowed trunks a source of water for elephants who dig with their tusks. Afternoon charter flight back to Arusha. Private vehicle transfer to your hotel or onward connection.','location'=>'Tarangire / Arusha','accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_name'=>'Private Land Cruiser (luxury spec)','vehicle_rate'=>320,'park_fee_name'=>'Tarangire NP','park_adult_fee'=>59,'meals_cost_pp'=>60,'activities'=>[['name'=>'Private morning game drive','type'=>'included'],['name'=>'Charter flight Tarangire→Arusha','type'=>'included']],'other_items'=>[]]
]);

$luxuryItinId = upsertItinerary($db, [
    'name' => 'Luxury Safari — 6 Nights', 'slug' => 'luxury-safari-6-nights',
    'duration_days' => 6,
    'overview' => 'Tanzania\'s finest lodges, private charter flights, a hot air balloon over the Serengeti, and exclusive access to wildlife concessions where the rules of ordinary safaris do not apply.',
    'accommodation_level' => 'luxury',
    'days' => $luxuryDays,
    'includes' => json_encode(['Private charter flights Arusha↔Serengeti, Serengeti↔Ngorongoro, Ngorongoro↔Tarangire↔Arusha','Hot air balloon flight (60–90 min) with champagne breakfast','All park fees and concession fees','Private 4WD Land Cruiser (luxury spec) with pop-up roof','Expert private TANAPA-licensed guide','6 nights at Four Seasons Serengeti & &Beyond Ngorongoro Crater Lodge','All meals — full board gourmet dining','Ngorongoro Crater full-day private descent','Walking safari in private concession','Spa treatment (1 per person, Serengeti)','All bottled water and in-vehicle drinks','Airport/hotel transfers in Arusha']),
    'excludes' => json_encode(['International flights','Tanzania visa','Personal travel insurance','Additional spa treatments','Premium alcoholic beverages beyond lodge package','Gratuities (guide, lodge staff, balloon crew)','Optional Maasai village cultural visit']),
    'base_price_pax' => 2900.00, 'is_active' => 1, 'sort_order' => 2
]);
linkPackage($db, 'luxury-safari', $luxuryItinId);

makeQuotation($db, 'Luxury Safari', $luxuryItinId, 2, 6, [
    ['category'=>'accommodation','description'=>'Four Seasons Safari Lodge Serengeti — 3 nights (2 pax)','unit_price'=>680,'quantity_num'=>3,'quantity_str'=>'3 nights','amount'=>2040],
    ['category'=>'accommodation','description'=>'&Beyond Ngorongoro Crater Lodge — 2 nights (2 pax)','unit_price'=>920,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>1840],
    ['category'=>'transport','description'=>'Private charter flights (all legs)','unit_price'=>600,'quantity_num'=>1,'quantity_str'=>'return circuit','amount'=>1800],
    ['category'=>'transport','description'=>'Private Land Cruiser — 6 days','unit_price'=>320,'quantity_num'=>6,'quantity_str'=>'6 days','amount'=>1920],
    ['category'=>'activities','description'=>'Hot air balloon flight — 2 persons × $700','unit_price'=>700,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>1400],
    ['category'=>'activities','description'=>'Spa treatment — 2 persons','unit_price'=>120,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>240],
    ['category'=>'park_fees','description'=>'Serengeti NP — 3 days × 2 adults × $82','unit_price'=>82,'quantity_num'=>6,'quantity_str'=>'6 entries','amount'=>492],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 2 days × 2 adults × $80','unit_price'=>80,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>320],
    ['category'=>'park_fees','description'=>'Tarangire NP — 1 day × 2 adults × $59','unit_price'=>59,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>118],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent fees × 2','unit_price'=>70,'quantity_num'=>2,'quantity_str'=>'2 descents','amount'=>140],
    ['category'=>'meals','description'=>'Full board gourmet dining — 6 days × 2 pax × $125/pp','unit_price'=>125,'quantity_num'=>12,'quantity_str'=>'12 person-days','amount'=>1500],
    ['category'=>'other','description'=>'Service, admin & coordination','unit_price'=>350,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>350],
], 'highend');

// ══════════════════════════════════════════════════════════════
// 3. COMFORT SAFARI — 5 Nights (Lake Manyara + Serengeti + Ngorongoro)
// ══════════════════════════════════════════════════════════════
echo "\n[3] Comfort Safari — 5 Nights\n";

$comfortDays = json_encode([
    ['day'=>1,'title'=>'Arusha → Lake Manyara National Park','description'=>'Drive west from Arusha through the fertile Arusha District farmland. Descend into the Great Rift Valley to Lake Manyara — a soda lake fringed by groundwater forest where tree-climbing lions drape themselves over mahogany branches. Watch thousands of pink flamingos wade in the shallows and hippos crowd the lake\'s edge as the afternoon light turns golden.','location'=>'Lake Manyara','accommodation_name'=>'Lake Manyara Serena Safari Lodge','accommodation_rate'=>165,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Lake Manyara NP','park_adult_fee'=>53,'meals_cost_pp'=>45,'activities'=>[['name'=>'Afternoon game drive','type'=>'included'],['name'=>'Hippo pool visit','type'=>'included']],'other_items'=>[]],
    ['day'=>2,'title'=>'Manyara → Ngorongoro Conservation Area','description'=>'Morning drive from Manyara climbing the Rift Valley escarpment. Stop at a Maasai market in Karatu — one of Tanzania\'s most authentic weekly markets. Drive through lush Karatu highlands coffee and wheat farms to the Ngorongoro Crater rim. Afternoon at leisure with views across the caldera as the mist moves in from the east.','location'=>'Ngorongoro','accommodation_name'=>'Ngorongoro Sopa Lodge','accommodation_rate'=>195,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>48,'activities'=>[['name'=>'Karatu market visit','type'=>'included'],['name'=>'Crater rim sunset walk','type'=>'included']],'other_items'=>[]],
    ['day'=>3,'title'=>'Ngorongoro Crater → Serengeti','description'=>'Dawn descent into the Ngorongoro Crater — the world\'s largest intact caldera. Four hours in the crater tracking all Big Five including black rhino. Drive out through the highlands into the Serengeti via the Olduvai Gorge — where homo sapiens walked 1.8 million years ago. Late afternoon arrival at a comfortable Serengeti camp.','location'=>'Central Serengeti','accommodation_name'=>'Serengeti Sopa Lodge','accommodation_rate'=>195,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>48,'activities'=>[['name'=>'Ngorongoro Crater game drive','type'=>'included'],['name'=>'Olduvai Gorge stop (optional)','type'=>'paid'],['name'=>'Evening Serengeti drive','type'=>'included']],'other_items'=>[['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle']]],
    ['day'=>4,'title'=>'Full Day Central Serengeti','description'=>'A complete day in the Seronera Valley — the Big Five heartland where rivers of wildebeest flow north and south by season. Your guide tracks lion prides from fresh pugmarks at dawn. Cheetah on termite mounds. Leopard in acacia trees above the Seronera River. Picnic lunch at a scenic kopje. Late drive as hippos emerge from the river at dusk.','location'=>'Central Serengeti','accommodation_name'=>'Serengeti Sopa Lodge','accommodation_rate'=>195,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>48,'activities'=>[['name'=>'Dawn drive — lion tracking','type'=>'included'],['name'=>'Kopje picnic lunch','type'=>'included'],['name'=>'Afternoon cheetah & leopard drive','type'=>'included']],'other_items'=>[]],
    ['day'=>5,'title'=>'Serengeti → Tarangire → Arusha','description'=>'Final morning game drive in the Serengeti before driving east. Stop in Tarangire for a three-hour afternoon game drive along the river where elephant families feed in the shallows. The tree-climbing elephants of Tarangire are unique in Africa. Late afternoon return to Arusha, arriving in time for dinner at your hotel.','location'=>'Tarangire / Arusha','accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Tarangire NP','park_adult_fee'=>59,'meals_cost_pp'=>35,'activities'=>[['name'=>'Serengeti morning drive','type'=>'included'],['name'=>'Tarangire afternoon drive','type'=>'included']],'other_items'=>[]]
]);

$comfortItinId = upsertItinerary($db, [
    'name' => 'Comfort Safari — 5 Nights', 'slug' => 'comfort-safari-5-nights',
    'duration_days' => 5,
    'overview' => 'Lake Manyara\'s tree-climbing lions, Ngorongoro Crater\'s ancient caldera, and Serengeti\'s Big Five — Tanzania\'s northern circuit in superb comfort at exceptional value.',
    'accommodation_level' => 'midrange',
    'days' => $comfortDays,
    'includes' => json_encode(['All park entry fees (Lake Manyara, Ngorongoro, Serengeti, Tarangire)','Private Toyota Land Cruiser 4WD with pop-up roof','Expert TANAPA-licensed guide','5 nights accommodation (mid-range lodges)','All meals on safari','Ngorongoro Crater descent','All game drives in parks','Drinking water in vehicle','Arusha transfers']),
    'excludes' => json_encode(['International flights','Tanzania visa','Travel insurance','Olduvai Gorge fee (USD 35 optional)','Tips and gratuities','Personal items','Alcoholic beverages']),
    'base_price_pax' => 1550.00, 'is_active' => 1, 'sort_order' => 3
]);
linkPackage($db, 'comfort-safari', $comfortItinId);

makeQuotation($db, 'Comfort Safari', $comfortItinId, 2, 5, [
    ['category'=>'accommodation','description'=>'Lake Manyara Serena Safari Lodge — 1 night','unit_price'=>165,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>165],
    ['category'=>'accommodation','description'=>'Ngorongoro Sopa Lodge — 1 night','unit_price'=>195,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>195],
    ['category'=>'accommodation','description'=>'Serengeti Sopa Lodge — 2 nights','unit_price'=>195,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>390],
    ['category'=>'transport','description'=>'Private Toyota Land Cruiser 4WD — 5 days','unit_price'=>250,'quantity_num'=>5,'quantity_str'=>'5 days','amount'=>1250],
    ['category'=>'park_fees','description'=>'Lake Manyara NP — 1 day × 2 adults × $53','unit_price'=>53,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>106],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 2 days × 2 adults × $80','unit_price'=>80,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>320],
    ['category'=>'park_fees','description'=>'Serengeti NP — 2 days × 2 adults × $82','unit_price'=>82,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>328],
    ['category'=>'park_fees','description'=>'Tarangire NP — 1 day × 2 adults × $59','unit_price'=>59,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>118],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent','unit_price'=>70,'quantity_num'=>1,'quantity_str'=>'1 vehicle','amount'=>70],
    ['category'=>'meals','description'=>'Full board meals — 5 days × 2 pax × $46/pp','unit_price'=>46,'quantity_num'=>10,'quantity_str'=>'10 person-days','amount'=>460],
    ['category'=>'other','description'=>'Service & administration','unit_price'=>130,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>130],
], 'midrange');

// ══════════════════════════════════════════════════════════════
// 4. SAFARI + ZANZIBAR — 7 Nights
// ══════════════════════════════════════════════════════════════
echo "\n[4] Safari + Zanzibar — 7 Nights\n";

$zanzibarDays = json_encode([
    ['day'=>1,'title'=>'Arrive Arusha → Tarangire','description'=>'Arrive at Kilimanjaro International Airport. Your Safari Zangu guide meets you and drives directly to Tarangire National Park. Afternoon game drive along the iconic Tarangire River — a ribbon of life in the dry season that draws extraordinary concentrations of wildlife. Elephants, zebra, wildebeest, and predators all vie for access to the water.','location'=>'Tarangire NP','accommodation_name'=>'Tarangire Safari Lodge','accommodation_rate'=>180,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Tarangire NP','park_adult_fee'=>59,'meals_cost_pp'=>45,'activities'=>[['name'=>'Afternoon game drive','type'=>'included']],'other_items'=>[]],
    ['day'=>2,'title'=>'Tarangire → Serengeti (via Ngorongoro Highlands)','description'=>'Morning drive through the Ngorongoro highlands — green and fertile, a world away from the plains below. Cross into the Serengeti ecosystem as the land opens into infinite golden grassland. Afternoon arrival and game drive in the Seronera Valley.','location'=>'Central Serengeti','accommodation_name'=>'Serengeti Serena Safari Lodge','accommodation_rate'=>220,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>48,'activities'=>[['name'=>'En-route game viewing','type'=>'included'],['name'=>'Seronera Valley afternoon drive','type'=>'included']],'other_items'=>[]],
    ['day'=>3,'title'=>'Full Day Serengeti — Migration & Big Five','description'=>'Full day tracking the Great Wildebeest Migration through the Seronera Valley. Over 1.5 million wildebeest move in a seasonal circuit — your guide knows their current location. Big cats are year-round residents: lions often hunt in the morning, cheetahs in the afternoon, and leopards use the riverine forests at dusk.','location'=>'Central Serengeti','accommodation_name'=>'Serengeti Serena Safari Lodge','accommodation_rate'=>220,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Serengeti NP','park_adult_fee'=>82,'meals_cost_pp'=>50,'activities'=>[['name'=>'Dawn migration tracking','type'=>'included'],['name'=>'Bush picnic lunch','type'=>'included'],['name'=>'Afternoon Big Five drive','type'=>'included']],'other_items'=>[]],
    ['day'=>4,'title'=>'Serengeti → Ngorongoro Crater','description'=>'Morning drive from Serengeti to Ngorongoro. Descend into the crater — Africa\'s most concentrated Big Five zone in the world\'s largest intact caldera. Four hours in the crater tracking rhino, lion, elephant, and flamingo. Evening on the crater rim with the spectacular view spread below.','location'=>'Ngorongoro','accommodation_name'=>'Ngorongoro Serena Safari Lodge','accommodation_rate'=>230,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>52,'activities'=>[['name'=>'Ngorongoro Crater descent','type'=>'included'],['name'=>'Black rhino tracking','type'=>'included']],'other_items'=>[['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle']]],
    ['day'=>5,'title'=>'Fly to Zanzibar — Stone Town Arrival','description'=>'Morning flight from Kilimanjaro to Zanzibar. Transfer to your beach resort. Afternoon exploring Stone Town — a UNESCO World Heritage maze of carved doors, Persian courtyards, Indian bazaars, and Swahili architecture that 1,000 years of trading history built. Evening spice market and rooftop dinner over the dhow harbour.','location'=>'Zanzibar — Stone Town','accommodation_name'=>'Zanzibar Serena Inn','accommodation_rate'=>210,'accommodation_rooms'=>1,'vehicle_name'=>'Airport transfer','vehicle_rate'=>60,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>60,'activities'=>[['name'=>'Stone Town guided walking tour','type'=>'included'],['name'=>'Spice market visit','type'=>'included'],['name'=>'Rooftop sunset dinner','type'=>'included']],'other_items'=>[['name'=>'Domestic flight Arusha→Zanzibar','amount'=>280,'per'=>'person']]],
    ['day'=>6,'title'=>'Zanzibar — Indian Ocean Beach Day','description'=>'A full day on Zanzibar\'s legendary white sand beaches. Nungwi or Kendwa beach — powder-white sand, warm turquoise water, and the gentle trade wind. Afternoon snorkelling on the coral reef. Optional: sunset dhow cruise with traditional Swahili dinner served at anchor.','location'=>'Zanzibar — North Coast','accommodation_name'=>'Nungwi Dreams by Warwick','accommodation_rate'=>190,'accommodation_rooms'=>1,'vehicle_name'=>'Transfer','vehicle_rate'=>40,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>55,'activities'=>[['name'=>'Beach day at Nungwi/Kendwa','type'=>'included'],['name'=>'Snorkelling on coral reef','type'=>'included'],['name'=>'Sunset dhow cruise','type'=>'included']],'other_items'=>[]],
    ['day'=>7,'title'=>'Zanzibar → Spice Farm → Departure','description'=>'Morning spice farm tour — smell and taste cloves, vanilla, cinnamon, cardamom, and black pepper growing as they have for centuries. Traditional Swahili lunch using the same spices. Transfer to Zanzibar International Airport for your homeward connection.','location'=>'Zanzibar','accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_name'=>'Transfer','vehicle_rate'=>40,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>35,'activities'=>[['name'=>'Spice farm guided tour','type'=>'included'],['name'=>'Swahili cooking lunch','type'=>'included']],'other_items'=>[]]
]);

$zanzibarItinId = upsertItinerary($db, [
    'name' => 'Safari + Zanzibar — 7 Nights', 'slug' => 'safari-zanzibar-7-nights',
    'duration_days' => 7,
    'overview' => 'Tanzania\'s complete experience — three days of Serengeti Big Five, the ancient Ngorongoro Crater, then white sand Indian Ocean beaches and the UNESCO spice island of Zanzibar.',
    'accommodation_level' => 'midrange',
    'days' => $zanzibarDays,
    'includes' => json_encode(['All Tanzania national park fees (Tarangire, Serengeti, Ngorongoro)','Private Toyota Land Cruiser 4WD with pop-up roof','Professional safari guide','Domestic flight Arusha → Zanzibar (economy)','Stone Town guided walking tour','Spice farm tour','Snorkelling on coral reef','Sunset dhow cruise','4 nights mid-range safari lodges','3 nights Zanzibar beach accommodation','All meals on safari (full board)','Breakfast + dinner in Zanzibar','Airport/hotel transfers throughout']),
    'excludes' => json_encode(['International flights','Tanzania visa','Travel insurance','Gratuities','Alcoholic beverages (beyond included)','Lunch in Zanzibar days 6 & 7','Optional activities (kitesurfing, deep sea fishing, Swahili cooking class)','Personal items']),
    'base_price_pax' => 2200.00, 'is_active' => 1, 'sort_order' => 4
]);
linkPackage($db, 'safari-zanzibar', $zanzibarItinId);

makeQuotation($db, 'Safari + Zanzibar', $zanzibarItinId, 2, 7, [
    ['category'=>'accommodation','description'=>'Tarangire Safari Lodge — 1 night (2 pax)','unit_price'=>180,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>180],
    ['category'=>'accommodation','description'=>'Serengeti Serena Safari Lodge — 2 nights (2 pax)','unit_price'=>220,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>440],
    ['category'=>'accommodation','description'=>'Ngorongoro Serena Safari Lodge — 1 night (2 pax)','unit_price'=>230,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>230],
    ['category'=>'accommodation','description'=>'Zanzibar Serena Inn — 1 night (2 pax)','unit_price'=>210,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>210],
    ['category'=>'accommodation','description'=>'Nungwi Dreams by Warwick — 2 nights (2 pax)','unit_price'=>190,'quantity_num'=>2,'quantity_str'=>'2 nights','amount'=>380],
    ['category'=>'transport','description'=>'Private Toyota Land Cruiser 4WD — 4 safari days','unit_price'=>250,'quantity_num'=>4,'quantity_str'=>'4 days','amount'=>1000],
    ['category'=>'transport','description'=>'Domestic flights Arusha→Zanzibar × 2 pax','unit_price'=>280,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>560],
    ['category'=>'transport','description'=>'Zanzibar road transfers','unit_price'=>140,'quantity_num'=>1,'quantity_str'=>'3 transfers','amount'=>140],
    ['category'=>'park_fees','description'=>'Tarangire NP — 1 day × 2 adults × $59','unit_price'=>59,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>118],
    ['category'=>'park_fees','description'=>'Serengeti NP — 2 days × 2 adults × $82','unit_price'=>82,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>328],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 1 day × 2 adults × $80','unit_price'=>80,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>160],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent fee','unit_price'=>70,'quantity_num'=>1,'quantity_str'=>'1 vehicle','amount'=>70],
    ['category'=>'activities','description'=>'Stone Town tour, Spice farm, Dhow cruise, Snorkelling','unit_price'=>120,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>240],
    ['category'=>'meals','description'=>'Full board safari — 4 days × 2 pax × $49/pp','unit_price'=>49,'quantity_num'=>8,'quantity_str'=>'8 person-days','amount'=>392],
    ['category'=>'meals','description'=>'Zanzibar BB+dinner — 3 days × 2 pax × $58/pp','unit_price'=>58,'quantity_num'=>6,'quantity_str'=>'6 person-days','amount'=>348],
    ['category'=>'other','description'=>'Service & administration','unit_price'=>200,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>200],
], 'midrange');

// ══════════════════════════════════════════════════════════════
// 5. CULTURAL SAFARI — 4 Nights
// ══════════════════════════════════════════════════════════════
echo "\n[5] Cultural Safari — 4 Nights\n";

$culturalDays = json_encode([
    ['day'=>1,'title'=>'Arusha Cultural Immersion','description'=>'Begin in Arusha — Tanzania\'s safari capital and a vibrant crossroads of cultures. Morning visit to the Arusha Cultural Heritage Centre — a world-class collection of Maasai, Makonde, and Tingatinga art. Afternoon Maasai community walk with elders. Learn traditional fire-making, listen to oral history of the Great Rift Valley, and join a women\'s beadwork circle. Optional: traditional Maasai meat feast in the evening.','location'=>'Arusha','accommodation_name'=>'Arusha Coffee Lodge','accommodation_rate'=>160,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>200,'park_fee_name'=>'','park_adult_fee'=>0,'meals_cost_pp'=>45,'activities'=>[['name'=>'Cultural Heritage Centre visit','type'=>'included'],['name'=>'Maasai community walk & beadwork','type'=>'included'],['name'=>'Traditional fire-making','type'=>'included']],'other_items'=>[]],
    ['day'=>2,'title'=>'Lake Manyara — Flamingos & Forest Elephants','description'=>'Drive to Lake Manyara — a Rift Valley soda lake famous for unique tree-climbing lions, large elephant herds that move through groundwater forest, and thousands of pink flamingos. Morning game drive. Afternoon visit to a local farmer\'s cooperative in Karatu producing premium Tanzanian coffee and wine grapes — rare at this altitude. Taste and tour included.','location'=>'Lake Manyara / Karatu','accommodation_name'=>'Karatu Tented Camp','accommodation_rate'=>120,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>200,'park_fee_name'=>'Lake Manyara NP','park_adult_fee'=>53,'meals_cost_pp'=>40,'activities'=>[['name'=>'Lake Manyara game drive','type'=>'included'],['name'=>'Karatu coffee & wine farm tour','type'=>'included'],['name'=>'Flamingo viewing','type'=>'included']],'other_items'=>[]],
    ['day'=>3,'title'=>'Ngorongoro Crater & Olduvai Gorge','description'=>'Dawn descent into the Ngorongoro Crater. But first — stop at Olduvai Gorge, where Mary Leakey discovered the 1.8-million-year-old footprints of our earliest human ancestors. The on-site museum puts human evolution into perspective before you spend the afternoon surrounded by the same animals that shaped our species. Full crater descent with picnic lunch on the crater floor.','location'=>'Ngorongoro','accommodation_name'=>'Ngorongoro Farm House','accommodation_rate'=>140,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>200,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>45,'activities'=>[['name'=>'Olduvai Gorge museum visit','type'=>'included'],['name'=>'Ngorongoro Crater descent','type'=>'included'],['name'=>'Crater floor picnic lunch','type'=>'included']],'other_items'=>[['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle'],['name'=>'Olduvai Gorge entrance','amount'=>35,'per'=>'person']]],
    ['day'=>4,'title'=>'Tarangire — Baobab Forest → Maasai Village → Arusha','description'=>'Morning game drive in Tarangire among the ancient baobab trees that may be 2,000 years old. Afternoon visit to a traditional Maasai boma — enter a family compound, learn about the Maasai calendar, cattle culture, and healing plants used for millennia. Join the warriors in a jumping dance and try your hand at a bow and arrow. Return to Arusha in the evening.','location'=>'Tarangire / Arusha','accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>200,'park_fee_name'=>'Tarangire NP','park_adult_fee'=>59,'meals_cost_pp'=>35,'activities'=>[['name'=>'Tarangire game drive','type'=>'included'],['name'=>'Traditional Maasai boma visit','type'=>'included'],['name'=>'Maasai jumping dance','type'=>'included'],['name'=>'Traditional bow & arrow','type'=>'included']],'other_items'=>[['name'=>'Maasai community fee','amount'=>30,'per'=>'person']]]
]);

$culturalItinId = upsertItinerary($db, [
    'name' => 'Cultural Safari — 4 Nights', 'slug' => 'cultural-safari-4-nights',
    'duration_days' => 4,
    'overview' => 'Tanzania\'s wildlife alongside its living cultures — Maasai communities, Olduvai Gorge human origins, Karatu farm tours, and genuine cultural exchange woven into the safari experience.',
    'accommodation_level' => 'budget',
    'days' => $culturalDays,
    'includes' => json_encode(['Park fees (Lake Manyara, Ngorongoro, Tarangire)','Private Toyota Land Cruiser 4WD','TANAPA-licensed safari guide with cultural expertise','Maasai community walk & beadwork session','Karatu coffee farm visit & tasting','Olduvai Gorge museum entry','Ngorongoro Crater descent','Traditional Maasai boma visit','4 nights accommodation','All meals on safari','Arusha Cultural Heritage Centre','Arusha transfers']),
    'excludes' => json_encode(['International flights','Tanzania visa','Travel insurance','Maasai community fee (USD 30 pp — paid locally)','Optional traditional Maasai feast (USD 25 pp)','Personal items','Alcoholic beverages','Tips and gratuities']),
    'base_price_pax' => 1100.00, 'is_active' => 1, 'sort_order' => 5
]);
linkPackage($db, 'cultural-safari', $culturalItinId);

makeQuotation($db, 'Cultural Safari', $culturalItinId, 2, 4, [
    ['category'=>'accommodation','description'=>'Arusha Coffee Lodge — 1 night (2 pax)','unit_price'=>160,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>160],
    ['category'=>'accommodation','description'=>'Karatu Tented Camp — 1 night (2 pax)','unit_price'=>120,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>120],
    ['category'=>'accommodation','description'=>'Ngorongoro Farm House — 1 night (2 pax)','unit_price'=>140,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>140],
    ['category'=>'transport','description'=>'Private Toyota Land Cruiser 4WD — 4 days','unit_price'=>200,'quantity_num'=>4,'quantity_str'=>'4 days','amount'=>800],
    ['category'=>'park_fees','description'=>'Lake Manyara NP — 1 day × 2 adults × $53','unit_price'=>53,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>106],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 1 day × 2 adults × $80','unit_price'=>80,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>160],
    ['category'=>'park_fees','description'=>'Tarangire NP — 1 day × 2 adults × $59','unit_price'=>59,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>118],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent fee','unit_price'=>70,'quantity_num'=>1,'quantity_str'=>'1 vehicle','amount'=>70],
    ['category'=>'activities','description'=>'Olduvai Gorge entry × 2 pax','unit_price'=>35,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>70],
    ['category'=>'activities','description'=>'Maasai community & cultural program × 2 pax','unit_price'=>30,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>60],
    ['category'=>'activities','description'=>'Karatu coffee farm + Cultural Heritage Centre','unit_price'=>25,'quantity_num'=>2,'quantity_str'=>'2 persons','amount'=>50],
    ['category'=>'meals','description'=>'Full board meals — 4 days × 2 pax × $41/pp','unit_price'=>41,'quantity_num'=>8,'quantity_str'=>'8 person-days','amount'=>328],
    ['category'=>'other','description'=>'Service & administration','unit_price'=>100,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>100],
], 'budget');

// ══════════════════════════════════════════════════════════════
// 6. SHORT SAFARI — 3 Nights (Tarangire + Ngorongoro)
// ══════════════════════════════════════════════════════════════
echo "\n[6] Short Safari — 3 Nights\n";

$shortDays = json_encode([
    ['day'=>1,'title'=>'Arusha → Tarangire NP — Elephant Herds','description'=>'Perfect for those with limited time — Tarangire delivers an exceptional safari experience. Drive south from Arusha and enter Tarangire in time for a full afternoon game drive. The Tarangire River is the lifeblood of this park in the dry season — elephant herds of 200+ wade and play in the shallows. The density of wildlife along the river rivals any park in Africa.','location'=>'Tarangire NP','accommodation_name'=>'Tarangire Treetops Lodge','accommodation_rate'=>195,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Tarangire NP','park_adult_fee'=>59,'meals_cost_pp'=>48,'activities'=>[['name'=>'Full afternoon game drive','type'=>'included'],['name'=>'Sundowner at the river','type'=>'included'],['name'=>'Night walk (optional)','type'=>'paid']],'other_items'=>[]],
    ['day'=>2,'title'=>'Tarangire → Ngorongoro Crater','description'=>'Early morning final drive in Tarangire before the heat builds. Drive east through the green Karatu highlands, climbing from the Rift Valley floor to the Ngorongoro Crater rim at 2,200 metres. Descend into the world\'s greatest natural amphitheatre in the afternoon — 25,000 large animals in a self-contained ecosystem. Lion prides hunt in the open; hippos crowd the hippo pool; black rhino graze in the distance.','location'=>'Ngorongoro','accommodation_name'=>'Ngorongoro Sopa Lodge','accommodation_rate'=>195,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>50,'activities'=>[['name'=>'Morning Tarangire game drive','type'=>'included'],['name'=>'Afternoon Ngorongoro Crater descent','type'=>'included'],['name'=>'Crater rim evening walk','type'=>'included']],'other_items'=>[['name'=>'Crater descent fee','amount'=>70,'per'=>'vehicle']]],
    ['day'=>3,'title'=>'Ngorongoro Full Day → Arusha','description'=>'The full Ngorongoro Crater experience. Enter at first light when lions are most active and hippos haven\'t returned to the water yet. Spend six hours exploring the crater floor — the caldera walls create a contained ecosystem where prey cannot escape and predators live in extraordinary density. Picnic lunch on the crater floor. Drive out through the highlands in the afternoon, returning to Arusha for dinner.','location'=>'Ngorongoro / Arusha','accommodation_name'=>'','accommodation_rate'=>0,'accommodation_rooms'=>1,'vehicle_name'=>'Toyota Land Cruiser 4WD','vehicle_rate'=>250,'park_fee_name'=>'Ngorongoro CA','park_adult_fee'=>80,'meals_cost_pp'=>40,'activities'=>[['name'=>'Full day crater game drive','type'=>'included'],['name'=>'Crater floor bush picnic','type'=>'included'],['name'=>'Return transfer to Arusha','type'=>'included']],'other_items'=>[]]
]);

$shortItinId = upsertItinerary($db, [
    'name' => 'Short Safari — 3 Nights', 'slug' => 'short-safari-3-nights',
    'duration_days' => 3,
    'overview' => 'Tanzania\'s two most iconic destinations in just 3 nights — Tarangire\'s massive elephant herds and Ngorongoro\'s legendary crater. Maximum wildlife, minimum time.',
    'accommodation_level' => 'midrange',
    'days' => $shortDays,
    'includes' => json_encode(['All park fees (Tarangire NP, Ngorongoro CA)','Private Toyota Land Cruiser 4WD with pop-up roof','Professional TANAPA-licensed safari guide','3 nights accommodation','All meals on safari (full board)','Tarangire full afternoon game drive','Ngorongoro Crater descent (× 2)','Drinking water in vehicle','Arusha transfers']),
    'excludes' => json_encode(['International flights','Tanzania visa','Travel insurance','Night walk in Tarangire (USD 25 pp optional)','Gratuities','Personal items','Alcoholic beverages']),
    'base_price_pax' => 1049.00, 'is_active' => 1, 'sort_order' => 6
]);
linkPackage($db, 'short-safari', $shortItinId);

makeQuotation($db, 'Short Safari', $shortItinId, 2, 3, [
    ['category'=>'accommodation','description'=>'Tarangire Treetops Lodge — 1 night (2 pax)','unit_price'=>195,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>195],
    ['category'=>'accommodation','description'=>'Ngorongoro Sopa Lodge — 1 night (2 pax)','unit_price'=>195,'quantity_num'=>1,'quantity_str'=>'1 night','amount'=>195],
    ['category'=>'transport','description'=>'Private Toyota Land Cruiser 4WD — 3 days','unit_price'=>250,'quantity_num'=>3,'quantity_str'=>'3 days','amount'=>750],
    ['category'=>'park_fees','description'=>'Tarangire NP — 1 day × 2 adults × $59','unit_price'=>59,'quantity_num'=>2,'quantity_str'=>'2 entries','amount'=>118],
    ['category'=>'park_fees','description'=>'Ngorongoro CA — 2 days × 2 adults × $80','unit_price'=>80,'quantity_num'=>4,'quantity_str'=>'4 entries','amount'=>320],
    ['category'=>'park_fees','description'=>'Ngorongoro Crater descent fee × 2','unit_price'=>70,'quantity_num'=>2,'quantity_str'=>'2 descents','amount'=>140],
    ['category'=>'meals','description'=>'Full board meals — 3 days × 2 pax × $46/pp','unit_price'=>46,'quantity_num'=>6,'quantity_str'=>'6 person-days','amount'=>276],
    ['category'=>'other','description'=>'Service & administration','unit_price'=>100,'quantity_num'=>1,'quantity_str'=>'fixed','amount'=>100],
], 'midrange');

// ══════════════════════════════════════════════════════════════
// DONE
// ══════════════════════════════════════════════════════════════
echo "\n══════════════════════════════════════════\n";
echo "  ALL ITINERARIES & QUOTATIONS SEEDED\n";
echo "══════════════════════════════════════════\n\n";

// Verify
$pkgs = $db->query("SELECT p.name, i.name as itin, i.duration_days, i.base_price_pax,
                           JSON_LENGTH(i.days) as days_count
                    FROM packages p JOIN itineraries i ON i.id=p.itinerary_id
                    WHERE p.is_active=1 ORDER BY p.sort_order")->fetchAll();
echo "PACKAGES → ITINERARIES:\n";
foreach ($pkgs as $p) {
    echo "  ✓ {$p['name']} → {$p['itin']} ({$p['days_count']} days, \${$p['base_price_pax']}/pp)\n";
}

$quots = $db->query("SELECT package_name, grand_total, per_person, status FROM quotations ORDER BY id")->fetchAll();
echo "\nQUOTATIONS:\n";
foreach ($quots as $q) {
    echo "  ✓ {$q['package_name']} — Grand: \${$q['grand_total']} | Per Person: \${$q['per_person']} | Status: {$q['status']}\n";
}