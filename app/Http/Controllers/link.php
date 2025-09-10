<?php
$target = 'h:/root/home/nsiacctg-001/www/employeeportal/storage/app/public/timekeeping_images';
$shortcut = 'h:/root/home/nsiacctg-001/www/employeeportal/public/storage/timekeeping_images';

if (symlink($target, $shortcut)) {
    echo "Symbolic link created successfully!";
} else {
    echo "Failed to create symbolic link.";
}
?>