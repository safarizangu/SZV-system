<?php
require_once __DIR__ . '/app/bootstrap.php';

$pdo = App\Models\Database::getInstance();
$teamMembers = [];
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'team_members'")->fetchAll();
    if (!empty($tables)) {
        $teamMembers = $pdo->query("SELECT * FROM team_members WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();
    }
} catch (Exception $e) { $teamMembers = []; }

$fallback = [
    ['name'=>'Samson Gabba',  'role'=>'Founder & CEO',     'bio'=>'Disciplined and confident software engineer with network, committed to excellence and growth, leveraging technology and strategic thinking to enhance customer experiences for Safari Zangu Ventures.', 'linkedin_url'=>'https://www.linkedin.com/in/samson-gabba',   'email'=>'samson@safarizanguven.com'],
    ['name'=>'Kelvin Kazungu','role'=>'Co Founder',         'bio'=>'Sales and client relations professional with strong communication and customer engagement skills, experienced in building partnerships and delivering exceptional service.',                          'linkedin_url'=>'https://www.linkedin.com/in/kelvin-kazungu', 'email'=>'kelvin@safarizanguven.com'],
    ['name'=>'Sayi Kilaja',   'role'=>'Legal Consultant',   'bio'=>'Results-driven legal professional skilled in compliance, negotiation, and client relations, bringing trusted guidance and structured solutions.',                                                     'linkedin_url'=>'https://www.linkedin.com/in/sayi-kilaja',    'email'=>'sayi@safarizanguven.com'],
    ['name'=>'Penina Mwanga', 'role'=>'General Manager',    'bio'=>'Dynamic general manager with strong sales and operations experience, skilled in team leadership and customer engagement for Safari Zangu Ventures.',                                                   'linkedin_url'=>'https://www.linkedin.com/in/penina-mwanga',  'email'=>'penina@safarizanguven.com'],
];
$team   = !empty($teamMembers) ? $teamMembers : $fallback;
$delays = ['','fade-in-delay-1','fade-in-delay-2','fade-in-delay-3'];


$pageTitle = 'About Safari Zangu Ventures';
$pageDescription = 'Learn about Safari Zangu Ventures, our guides, mission and local expertise.';
$pageCanonical = 'https://safarizanguven.com/about.php';

ob_start();
include __DIR__ . '/views/about.php';
$content = ob_get_clean();
include __DIR__ . '/layouts/app.php';
