<?php
/**
 * SvelCMS 后台 - 应用设置
 * @var string $type
 * @var string $slug
 * @var array $schema
 * @var array $values
 * @var string $name
 */
?><div class="card">
  <h2 class="card-title"><?= e($name) ?> · 设置</h2>
  <?php if ($schema === []): ?>
    <p class="hint">这个应用没有声明可配置项。</p>
  <?php else: ?>
    <form method="post" action="<?= e(admin_url('market/settings')) ?>">
      <?= \Svel\Core\Csrf::field() ?>
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">

      <?php foreach ($schema as $field): ?>
        <?php
        $key = (string)($field['key'] ?? '');
        $label = (string)($field['label'] ?? $key);
        $ftype = (string)($field['type'] ?? 'text');
        $value = (string)($values[$key] ?? '');
        if ($key === '') {
            continue;
        }
        ?>
        <label class="field"><span><?= e($label) ?></span>
          <?php if ($ftype === 'select'): ?>
            <select name="cfg_<?= e($key) ?>">
              <?php foreach ((array)($field['options'] ?? []) as $opt): ?>
                <?php
                $ov = (string)(is_array($opt) ? ($opt['value'] ?? '') : $opt);
                $ol = (string)(is_array($opt) ? ($opt['label'] ?? $ov) : $opt);
                ?>
                <option value="<?= e($ov) ?>"<?= $value === $ov ? ' selected' : '' ?>><?= e($ol) ?></option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($ftype === 'number'): ?>
            <input type="number" name="cfg_<?= e($key) ?>" value="<?= e($value) ?>">
          <?php elseif ($ftype === 'textarea'): ?>
            <textarea name="cfg_<?= e($key) ?>" rows="4"><?= e($value) ?></textarea>
          <?php else: ?>
            <input type="text" name="cfg_<?= e($key) ?>" value="<?= e($value) ?>">
          <?php endif; ?>
          <?php if (!empty($field['default'])): ?>
            <p class="hint">默认值：<?= e((string)$field['default']) ?></p>
          <?php endif; ?>
        </label>
      <?php endforeach; ?>

      <div class="actions">
        <button class="btn btn-primary" type="submit">保存设置</button>
        <a class="btn" href="<?= e(admin_url('market')) ?>">返回市场</a>
      </div>
    </form>
  <?php endif; ?>
</div>