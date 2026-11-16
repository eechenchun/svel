/* Markdown 编辑器：实时预览、目录、源码与预览滚动同步（原生 JS，无依赖） */
(function () {
  'use strict';

  var root = document.querySelector('.mdeditor');
  if (!root) { return; }

  var ta = root.querySelector('#mdeditor-input');
  var pv = root.querySelector('#mdeditor-preview');
  var outlineBox = root.querySelector('#mdeditor-outline');
  var counter = root.querySelector('#mdeditor-count');
  var previewUrl = root.getAttribute('data-preview');
  var token = root.getAttribute('data-token');
  var delay = parseInt(root.getAttribute('data-delay') || '300', 10);
  if (isNaN(delay) || delay < 0) { delay = 300; }
  var tabSize = parseInt(root.getAttribute('data-tab') || '4', 10);
  if (isNaN(tabSize) || tabSize < 1) { tabSize = 4; }

  var timer = null;
  var lock = false;
  var headings = [];

  function lineHeight() {
    var lh = parseFloat(window.getComputedStyle(ta).lineHeight);
    return lh > 0 ? lh : 22;
  }

  function scanHeadings() {
    var lines = ta.value.split('\n');
    var out = [];
    for (var i = 0; i < lines.length; i++) {
      var m = /^(#{1,6})\s+(.+?)\s*#*\s*$/.exec(lines[i]);
      if (m) { out.push({ line: i, level: m[1].length, text: m[2] }); }
    }
    return out;
  }

  function buildOutline() {
    headings = scanHeadings();
    if (!outlineBox) { return; }
    outlineBox.innerHTML = '';
    headings.forEach(function (h) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      a.href = 'javascript:void(0)';
      a.className = 'lv' + h.level;
      a.textContent = h.text;
      a.addEventListener('click', function () { gotoHeading(h); });
      li.appendChild(a);
      outlineBox.appendChild(li);
    });
  }

  function previewHeadings() {
    return pv.querySelectorAll('h1,h2,h3,h4,h5,h6');
  }

  function gotoHeading(h) {
    var list = previewHeadings();
    var idx = headings.indexOf(h);
    if (idx >= 0 && list[idx]) {
      lock = true;
      pv.scrollTop = list[idx].offsetTop - pv.offsetTop - 8;
      ta.scrollTop = Math.max(0, h.line * lineHeight() - 8);
      window.setTimeout(function () { lock = false; }, 90);
    }
  }

  function syncFromSource() {
    if (lock) { return; }
    var list = previewHeadings();
    if (!list.length || !headings.length) { return; }
    var currentLine = ta.scrollTop / lineHeight();
    var idx = 0;
    for (var i = 0; i < headings.length; i++) {
      if (headings[i].line <= currentLine + 1) { idx = i; } else { break; }
    }
    lock = true;
    pv.scrollTop = list[idx].offsetTop - pv.offsetTop - 8;
    window.setTimeout(function () { lock = false; }, 60);
  }

  function syncFromPreview() {
    if (lock) { return; }
    var list = previewHeadings();
    if (!list.length || !headings.length) { return; }
    var idx = 0;
    for (var i = 0; i < list.length; i++) {
      if (list[i].offsetTop - pv.offsetTop <= pv.scrollTop + 24) { idx = i; } else { break; }
    }
    lock = true;
    ta.scrollTop = Math.max(0, headings[idx].line * lineHeight() - 8);
    window.setTimeout(function () { lock = false; }, 60);
  }

  function uploadImage(file, done) {
    var uploadUrl = root.getAttribute('data-upload');
    if (!uploadUrl) { window.alert('上传地址缺失'); return; }
    var fd = new FormData();
    fd.append('_token', token);
    fd.append('file', file);
    fetch(uploadUrl, { method: 'POST', credentials: 'same-origin', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { window.alert((data && data.msg) ? data.msg : '上传失败'); return; }
        done(data.url, data.width, data.height);
      })
      .catch(function () { window.alert('上传失败，请检查目录权限'); });
  }

  function pickImage(done) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/png,image/jpeg,image/webp,image/gif';
    input.addEventListener('change', function () {
      if (input.files && input.files[0]) { uploadImage(input.files[0], done); }
    });
    input.click();
  }

  function schedule() {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, delay);
  }

  function refresh() {
    if (!previewUrl || !token) { return; }
    var body = new URLSearchParams();
    body.set('content', ta.value);
    body.set('_token', token);

    return fetch(previewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
      body: body.toString()
    }).then(function (r) { return r.json(); }).then(function (data) {
      if (!data || typeof data.html !== 'string') { return; }
      pv.innerHTML = data.html;
      buildOutline();
      if (counter) { counter.textContent = (data.count || 0) + ' 字'; }
    }).catch(function () { /* 预览失败不打断编辑 */ });
  }

  function wrap(before, after, placeholder) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var selected = ta.value.slice(start, end) || placeholder || '';
    var text = before + selected + after;
    ta.setRangeText(text, start, end, 'end');
    if (!ta.value.slice(start, start + text.length).length) { /* noop */ }
    ta.focus();
    ta.selectionStart = start + before.length;
    ta.selectionEnd = start + before.length + selected.length;
    schedule();
  }

  function prefixLine(prefix) {
    var start = ta.value.lastIndexOf('\n', ta.selectionStart - 1) + 1;
    ta.setRangeText(prefix, start, start, 'end');
    ta.focus();
    schedule();
  }

  root.querySelectorAll('[data-md]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var action = btn.getAttribute('data-md');
      if (action === 'bold') { wrap('**', '**', '加粗文字'); }
      else if (action === 'italic') { wrap('*', '*', '斜体文字'); }
      else if (action === 'h2') { prefixLine('## '); }
      else if (action === 'h3') { prefixLine('### '); }
      else if (action === 'link') {
        var url = window.prompt('链接地址', 'https://');
        if (url) { wrap('[', '](' + url + ')', '链接文字'); }
      } else if (action === 'image') {
        pickImage(function (url) { wrap('![', '](' + url + ')', '图片说明'); schedule(); });
      } else if (action === 'quote') { prefixLine('> '); }
      else if (action === 'code') { wrap('\n```\n', '\n```\n', '代码'); }
      else if (action === 'ul') { prefixLine('- '); }
      else if (action === 'ol') { prefixLine('1. '); }
      else if (action === 'hr') { wrap('\n---\n', '', ''); }
      else if (action === 'refresh') { refresh(); }
    });
  });

  ta.addEventListener('input', schedule);
  ta.addEventListener('scroll', function () {
    window.requestAnimationFrame(syncFromSource);
  });
  pv.addEventListener('scroll', function () {
    window.requestAnimationFrame(syncFromPreview);
  });
  ta.addEventListener('keydown', function (e) {
    if (e.key === 'Tab') {
      e.preventDefault();
      ta.setRangeText(new Array(tabSize + 1).join(' '), ta.selectionStart, ta.selectionEnd, 'end');
      schedule();
    }
  });

  buildOutline();
  refresh();
})();