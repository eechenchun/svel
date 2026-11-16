/* 富文本编辑器：contenteditable + execCommand，提交时把 HTML 写入隐藏 textarea */
(function () {
  'use strict';

  var root = document.querySelector('.rteditor');
  if (!root) { return; }

  var area = root.querySelector('#rteditor-area');
  var value = root.querySelector('#rteditor-value');
  var counter = root.querySelector('#rteditor-count');
  var form = root.closest('form');

  // 原内容是 Markdown 时给出明确提示，避免误把源码当富文本改掉
  if (root.getAttribute('data-origin-format') !== 'html') {
    var warn = document.createElement('p');
    warn.className = 'rteditor-warn';
    warn.textContent = '这篇内容原本不是 HTML，富文本区不会显示旧正文；保存后格式会切换为 HTML，原 Markdown 源码保留在数据库中直至覆盖。';
    root.insertBefore(warn, area);
    area.innerHTML = '';
  }

  function updateCount() {
    if (counter) {
      var text = (area.textContent || '').replace(/\s+/g, '');
      counter.textContent = text.length + ' 字';
    }
  }

  function sync() {
    value.value = area.innerHTML;
    updateCount();
  }

  function uploadImage(file) {
    var uploadUrl = root.getAttribute('data-upload');
    if (!uploadUrl) { window.alert('上传地址缺失'); return; }
    var fd = new FormData();
    fd.append('_token', root.getAttribute('data-token') || '');
    fd.append('file', file);
    fetch(uploadUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { window.alert((data && data.msg) ? data.msg : '上传失败'); return; }
        area.focus();
        document.execCommand('insertHTML', false, '<img src="' + data.url + '" alt="">');
        sync();
      })
      .catch(function () { window.alert('上传失败，请检查目录权限'); });
  }

  function pickImage() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,image/gif';
    input.addEventListener('change', function () {
      if (input.files && input.files[0]) { uploadImage(input.files[0]); }
    });
    input.click();
  }

  root.querySelectorAll('[data-rt]').forEach(function (btn) {
    btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
    btn.addEventListener('click', function () {
      var action = btn.getAttribute('data-rt');
      area.focus();
      if (action === 'bold') { document.execCommand('bold'); }
      else if (action === 'italic') { document.execCommand('italic'); }
      else if (action === 'h2') { document.execCommand('formatBlock', false, 'h2'); }
      else if (action === 'h3') { document.execCommand('formatBlock', false, 'h3'); }
      else if (action === 'ul') { document.execCommand('insertUnorderedList'); }
      else if (action === 'quote') { document.execCommand('formatBlock', false, 'blockquote'); }
      else if (action === 'pre') { document.execCommand('formatBlock', false, 'pre'); }
      else if (action === 'clear') { document.execCommand('removeFormat'); }
      else if (action === 'link') {
        var href = window.prompt('链接地址', 'https://');
        if (href) { document.execCommand('createLink', false, href); }
      }
      else if (action === 'image') { pickImage(); }
      sync();
    });
  });

  area.addEventListener('input', sync);
  area.addEventListener('paste', function (e) {
    // 默认只接受纯文本，避免把外部样式与脚本粘进来；可在应用设置里关掉
    if (root.getAttribute('data-paste') !== '0') {
      e.preventDefault();
      var text = (e.clipboardData || window.clipboardData).getData('text/plain');
      document.execCommand('insertText', false, text);
      sync();
    }
  });

  if (form) {
    form.addEventListener('submit', sync);
  }

  sync();
})();