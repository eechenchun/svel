<?php
/**
 * SvelCMS 后台 - 内容编辑
 * @var array $post
 * @var array $categories
 * @var string $tagNames
 * @var string $editor
 * @var string $editorAssets
 * @var string $editorName
 */
$isPage = (string)$post['type'] === 'page';
$action = $post['id'] > 0
    ? admin_url('post/edit?id=' . (int)$post['id'])
    : admin_url('post/edit');
$cover = (string)$post['cover'];
$coverUrl = $cover !== '' ? (str_starts_with($cover, 'http') ? $cover : asset($cover)) : '';
?><form method="post" action="<?= e($action) ?>" class="post-form post-layout" id="post-form">
  <?= \Svel\Core\Csrf::field() ?>
  <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">

  <div class="post-main">
    <div class="card">
      <label class="field"><span>标题</span>
        <input type="text" name="title" value="<?= e($post['title']) ?>" required autofocus>
      </label>
      <label class="field"><span>slug（地址标识，可修改，改完旧地址 301 到新地址）</span>
        <input type="text" name="slug" value="<?= e($post['slug']) ?>" placeholder="留空自动生成">
        <p class="hint">当前地址：<?= e(post_url($post)) ?>；中文标题会自动落成 <?= $isPage ? 'page' : 'post' ?>-{id}</p>
      </label>
      <label class="field"><span>标签（逗号或空格分隔，不存在会自动创建）</span>
        <input type="text" name="tags" value="<?= e($tagNames) ?>">
      </label>
      <label class="field"><span>摘要（留空自动从正文截取）</span>
        <textarea name="excerpt" rows="3"><?= e($post['excerpt']) ?></textarea>
      </label>
    </div>

    <div class="card">
      <div class="actions" style="justify-content:space-between;margin-bottom:10px">
        <h2 class="card-title" style="margin:0">正文</h2>
        <span class="hint">当前编辑器：<?= e($editorName) ?></span>
      </div>
      <?= $editor ?>
    </div>

    <div class="card">
      <h2 class="card-title">封面与 SEO</h2>
      <label class="field"><span>封面图（可直接上传，也可填站内相对路径或完整 URL）</span>
        <div class="cover-field">
          <input type="text" name="cover" id="cover-input" value="<?= e($cover) ?>" placeholder="/storage/uploads/2026/11/xxx.jpg">
          <button class="btn btn-sm" type="button" id="cover-pick">上传图片</button>
          <input type="file" id="cover-file" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
        </div>
        <img id="cover-preview" class="cover-preview" src="<?= e($coverUrl) ?>" alt="封面预览"<?= $coverUrl === '' ? ' style="display:none"' : '' ?>>
        <p class="hint" id="cover-status"></p>
      </label>
      <div class="row">
        <label class="field"><span>SEO 标题（留空用「标题 - 站点名」）</span>
          <input type="text" name="seo_title" value="<?= e($post['seo_title']) ?>">
        </label>
        <label class="field"><span>SEO 描述（留空自动截取正文）</span>
          <input type="text" name="seo_desc" value="<?= e($post['seo_desc']) ?>">
        </label>
      </div>
      <label class="field"><span>SEO 关键词（逗号分隔，可空）</span>
        <input type="text" name="seo_keywords" value="<?= e($post['seo_keywords']) ?>">
      </label>
    </div>
  </div>

  <aside class="post-side">
    <div class="card">
      <h2 class="card-title">发布</h2>
      <button class="btn btn-primary" type="submit" style="width:100%">保存</button>
      <div class="actions" style="margin-top:10px">
        <a class="btn btn-sm" href="<?= e(admin_url('post' . ($isPage ? '?type=page' : ''))) ?>">返回列表</a>
        <?php if ((int)$post['id'] > 0): ?>
          <a class="btn btn-sm" href="<?= e(post_url($post)) ?>" target="_blank" rel="noopener">预览</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <h2 class="card-title">发布设置</h2>
      <label class="field"><span>状态</span>
        <select name="status">
          <option value="draft"<?= $post['status'] === 'draft' ? ' selected' : '' ?>>草稿</option>
          <option value="publish"<?= $post['status'] === 'publish' ? ' selected' : '' ?>>发布</option>
        </select>
      </label>
      <label class="field"><span>类型</span>
        <select name="type">
          <option value="post"<?= $isPage ? '' : ' selected' ?>>文章</option>
          <option value="page"<?= $isPage ? ' selected' : '' ?>>独立页面</option>
        </select>
      </label>
      <label class="field"><span>分类</span>
        <select name="category_id">
          <option value="0">未分类</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int)$cat['id'] ?>"<?= (int)$post['category_id'] === (int)$cat['id'] ? ' selected' : '' ?>><?= e($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span>置顶</span>
        <select name="is_top">
          <option value="0"<?= (int)$post['is_top'] === 0 ? ' selected' : '' ?>>不置顶</option>
          <option value="1"<?= (int)$post['is_top'] === 1 ? ' selected' : '' ?>>置顶</option>
        </select>
      </label>
      <label class="field"><span>发布时间</span>
        <input type="text" name="published_date" value="<?= (int)$post['published_at'] > 0 ? e(date('Y-m-d H:i', (int)$post['published_at'])) : '' ?>" placeholder="留空为保存时的时间">
      </label>
      <p class="hint">状态设为「发布」且发布时间为空时，会用保存时的时间。</p>
    </div>

    <?php if ((int)$post['id'] > 0): ?>
      <div class="card">
        <h2 class="card-title">删除</h2>
        <p class="hint">删除后不可恢复，相关标签关联会一并清理。</p>
        <button class="btn btn-danger" type="submit" form="delete-post" style="width:100%">删除这条内容</button>
      </div>
    <?php endif; ?>
  </aside>
</form>

<?php if ((int)$post['id'] > 0): ?>
  <form method="post" action="<?= e(admin_url('post/delete')) ?>" id="delete-post" onsubmit="return confirm('删除后不可恢复，确定删除吗？');" style="display:none">
    <?= \Svel\Core\Csrf::field() ?>
    <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
  </form>
<?php endif; ?>

<script>
(function () {
  var pick = document.getElementById('cover-pick');
  var file = document.getElementById('cover-file');
  var input = document.getElementById('cover-input');
  var preview = document.getElementById('cover-preview');
  var status = document.getElementById('cover-status');
  var tokenField = document.querySelector('#post-form input[name="_token"]');
  if (!pick || !file || !input || !preview) { return; }

  pick.addEventListener('click', function () { file.click(); });

  file.addEventListener('change', function () {
    if (!file.files || !file.files[0]) { return; }
    var fd = new FormData();
    fd.append('_token', tokenField ? tokenField.value : '');
    fd.append('file', file.files[0]);
    if (status) { status.textContent = '上传中…'; }

    fetch(<?= json_encode(url('admin/upload')) ?>, {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || !data.ok) {
        if (status) { status.textContent = (data && data.msg) ? data.msg : '上传失败'; }
        return;
      }
      input.value = data.url;
      preview.src = data.url;
      preview.style.display = 'block';
      if (status) { status.textContent = '已上传：' + data.url; }
    }).catch(function () {
      if (status) { status.textContent = '上传失败，请检查目录权限'; }
    });
  });
})();
</script>