#!/bin/bash
PHP=/usr/bin/php8.3
BASE=/var/www/html/safari-zangu
echo "====== SZV CRON STATUS $(date) ======"

echo ""
echo "CRONTAB:"
crontab -u www-data -l | grep safari | sed 's/^/  /'

echo ""
echo "EMAIL QUEUE:"
$PHP -r "
\$p = new PDO('mysql:host=localhost;dbname=safari_zangu;charset=utf8mb4','root','Sdg@201819');
\$rows = \$p->query('SELECT status, COUNT(*) c FROM email_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
foreach(\$rows as \$r) echo '  '.\$r['status'].': '.\$r['c'].PHP_EOL;
\$last = \$p->query('SELECT to_email,subject,status,COALESCE(sent_at,created_at) ts FROM email_queue ORDER BY id DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
echo PHP_EOL.'  Recent:'.PHP_EOL;
foreach(\$last as \$r) echo '  ['.\$r['status'].'] '.\$r['to_email'].' - '.substr(\$r['subject'],0,35).PHP_EOL;
"

echo ""
echo "REMINDERS:"
$PHP -r "
\$p = new PDO('mysql:host=localhost;dbname=safari_zangu;charset=utf8mb4','root','Sdg@201819');
\$rows = \$p->query('SELECT status, COUNT(*) c FROM booking_reminders GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
if(empty(\$rows)) echo '  None scheduled yet'.PHP_EOL;
foreach(\$rows as \$r) echo '  '.\$r['status'].': '.\$r['c'].PHP_EOL;
\$due = \$p->query('SELECT COUNT(*) FROM booking_reminders WHERE status=\"pending\" AND send_at<=NOW()+INTERVAL 24 HOUR')->fetchColumn();
echo '  Due next 24h: '.\$due.PHP_EOL;
"

echo ""
echo "EMAIL LOG (last 5):"
tail -5 /var/log/szv_email.log 2>/dev/null | sed 's/^/  /' || echo "  Empty"

echo ""
echo "REMINDERS LOG (last 5):"
tail -5 /var/log/szv_reminders.log 2>/dev/null | sed 's/^/  /' || echo "  Empty"

echo ""
echo "TEST RUNS:"
echo "  Email queue:"
sudo -u www-data $PHP $BASE/php/process_email_queue.php 2>&1 | sed 's/^/    /'
echo "  Reminders:"
sudo -u www-data $PHP $BASE/php/process_reminders.php 2>&1 | sed 's/^/    /'
