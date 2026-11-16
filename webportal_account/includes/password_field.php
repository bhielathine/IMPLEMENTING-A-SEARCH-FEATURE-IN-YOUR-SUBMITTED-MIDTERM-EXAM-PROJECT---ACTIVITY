<?php
$name = $name ?? 'password';
$label = $label ?? 'Password';
?>
<label><?= e($label) ?>
    <input type="password" name="<?= e($name) ?>" minlength="8"
           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}"
           title="At least 8 characters, with uppercase, lowercase, and a number">
</label>
<p class="hint">At least 8 characters, with an uppercase letter, a lowercase letter, and a number.</p>
