<?php
/**
 * Run this once on the server to generate bcrypt hashes for admin passwords:
 *   php generate_admin_hashes.php
 * Then paste the hashes into seed_admins.sql and DELETE this file.
 */
$admins = [
    'percy'    => 'ChangeMe_Percy1!',
    'yoliswa'  => 'ChangeMe_Yoliswa1!',
    'patso'    => 'ChangeMe_Patso1!',
    'mphoyame' => 'ChangeMe_Mphoyame1!',
];

foreach ($admins as $name => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "$name: $hash\n";
}
?>
