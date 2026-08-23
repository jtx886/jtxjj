// Hero slider
(function() {
    var slides = document.querySelectorAll('.hero-slide');
    var dots = document.querySelectorAll('.hero-dot');
    if(!slides.length) return;
    var current = 0;
    function goTo(i) {
        slides[current].classList.remove('active');
        dots[current] && dots[current].classList.remove('active');
        current = (i + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current] && dots[current].classList.add('active');
    }
    dots.forEach(function(d, i) {
        d.addEventListener('click', function() { goTo(i); });
    });
    setInterval(function() { goTo(current + 1); }, 6000);
})();

// Toggle favorite
function toggleFavorite(btn, mediaId, mediaType, title, poster, year) {
    btn = btn.closest('.media-fav-btn') || btn;
    var isActive = btn.classList.contains('active');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/favorite.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) {
            btn.classList.toggle('active');
            showToast(res.message, 'success');
        } else {
            showToast(res.message, 'error');
            if(res.need_login) {
                setTimeout(function(){ window.location.href = 'login.php?need_login=1'; }, 1200);
            }
        }
    };
    var action = isActive ? 'remove' : 'add';
    xhr.send('action=' + action + '&media_id=' + mediaId + '&media_type=' + mediaType +
             '&title=' + encodeURIComponent(title) + '&poster=' + encodeURIComponent(poster) +
             '&year=' + encodeURIComponent(year));
    return false;
}

// Remove from favorite (profile)
function removeFavorite(id, el) {
    if(!confirm('确定移除该收藏吗？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/favorite.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) {
            el.closest('.list-row').remove();
            showToast('已移除', 'success');
            checkEmptyFav();
        } else {
            showToast(res.message, 'error');
        }
    };
    xhr.send('action=remove&id=' + id);
}

function checkEmptyFav() {
    var list = document.getElementById('favList');
    if(list && list.querySelectorAll('.list-row').length === 0) {
        list.innerHTML = '<div class="empty-state"><span class="icon icon-heart"></span><div class="empty-state-text">暂无收藏</div><div class="empty-state-desc">去看看喜欢的电影吧~</div></div>';
    }
}

// Remove history
function removeHistory(id, el) {
    if(!confirm('确定删除该观看记录吗？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/history.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) {
            el.closest('.list-row').remove();
            showToast('已删除', 'success');
            checkEmptyHist();
        } else {
            showToast(res.message, 'error');
        }
    };
    xhr.send('action=remove&id=' + id);
}

function clearAllHistory() {
    if(!confirm('确定清空所有观看历史吗？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/history.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) { location.reload(); }
        else { showToast(res.message, 'error'); }
    };
    xhr.send('action=clear');
}

function checkEmptyHist() {
    var list = document.getElementById('histList');
    if(list && list.querySelectorAll('.list-row').length === 0) {
        list.innerHTML = '<div class="empty-state"><span class="icon icon-clock"></span><div class="empty-state-text">暂无观看记录</div><div class="empty-state-desc">看几部电影留下足迹吧~</div></div>';
    }
}

// Tabs (profile etc)
function switchTab(btn, tabName) {
    var parent = btn.closest('.tabs-wrap');
    parent.querySelectorAll('.tab-item').forEach(function(t){t.classList.remove('active');});
    btn.classList.add('active');
    parent.querySelectorAll('.tab-content').forEach(function(c){c.classList.remove('active');});
    var target = document.getElementById('tab-' + tabName);
    if(target) target.classList.add('active');
    if(history.replaceState) {
        var url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        history.replaceState(null, '', url);
    }
}

// Feedback
function toggleReplyForm(btn, fid) {
    var form = document.getElementById('reply-form-' + fid);
    form.classList.toggle('active');
    btn.innerHTML = form.classList.contains('active') ? '取消回复' : '回复';
}

function submitReply(fid) {
    var txt = document.getElementById('reply-text-' + fid);
    var content = txt.value.trim();
    if(!content) { showToast('请输入回复内容', 'error'); return; }
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/feedback.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) { showToast(res.message, 'success'); setTimeout(function(){location.reload();}, 800); }
        else { showToast(res.message, 'error'); if(res.need_login) setTimeout(function(){window.location='login.php?need_login=1';},1200); }
    };
    xhr.send('action=reply&feedback_id=' + fid + '&content=' + encodeURIComponent(content));
}

function toggleLike(btn, fid) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'api/feedback.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) {
            btn.classList.toggle('liked');
            btn.querySelector('.count').textContent = res.count;
        } else {
            showToast(res.message, 'error');
            if(res.need_login) setTimeout(function(){window.location='login.php?need_login=1';},1200);
        }
    };
    xhr.send('action=like&feedback_id=' + fid);
}

function toggleReplies(btn, fid) {
    var wrap = document.getElementById('replies-' + fid);
    wrap.classList.toggle('replies-hidden');
    btn.innerHTML = wrap.classList.contains('replies-hidden') ? '展开全部回复' : '收起回复';
}

// Detail: select season
function selectSeason(btn, seasonNum, tvId) {
    btn.parentElement.querySelectorAll('.season-tab').forEach(function(t){t.classList.remove('active');});
    btn.classList.add('active');
    document.getElementById('episodes-loading').style.display = 'block';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'api/season.php?tv_id=' + tvId + '&season=' + seasonNum, true);
    xhr.onload = function() {
        document.getElementById('episodes-loading').style.display = 'none';
        document.getElementById('season-episodes').innerHTML = xhr.responseText;
    };
    xhr.send();
}

// Avatar upload
function uploadAvatar() {
    var inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = 'image/*';
    inp.onchange = function() {
        if(!inp.files[0]) return;
        if(inp.files[0].size > 2 * 1024 * 1024) { showToast('图片不能大于2MB', 'error'); return; }
        var fd = new FormData();
        fd.append('avatar', inp.files[0]);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/avatar.php', true);
        xhr.onload = function() {
            var res = JSON.parse(xhr.responseText);
            if(res.success) { showToast('头像已更新', 'success'); setTimeout(function(){location.reload();}, 800); }
            else { showToast(res.message, 'error'); }
        };
        xhr.send(fd);
    };
    inp.click();
}

// Watch progress
var watchTimer = null;
function startWatchTimer(historyId) {
    if(!historyId) return;
    watchTimer = setInterval(function() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'api/history.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=update_seconds&id=' + historyId);
    }, 10000);
}

// Category filter
function filterCategory(el, type, genreId) {
    el.parentElement.querySelectorAll('.cat-tab').forEach(function(t){t.classList.remove('active');});
    el.classList.add('active');
    var grid = document.getElementById('mediaGrid');
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)">加载中...</div>';
    var url = 'api/category.php?type=' + type + (genreId ? '&genre=' + genreId : '');
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onload = function() { grid.innerHTML = xhr.responseText; };
    xhr.send();
}

// Admin: user ban
function banUser(userId) {
    var reason = prompt('请输入封禁原因：', '违反社区规则');
    if(reason === null) return;
    var days = prompt('请输入封禁天数（0为永久）：', '7');
    if(days === null) return;
    days = parseInt(days) || 0;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin/api/users.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) { showToast(res.message, 'success'); setTimeout(function(){location.reload();}, 800); }
        else { showToast(res.message, 'error'); }
    };
    xhr.send('action=ban&user_id=' + userId + '&reason=' + encodeURIComponent(reason) + '&days=' + days);
}

function unbanUser(userId) {
    if(!confirm('确定解封该用户吗？')) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin/api/users.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if(res.success) { showToast(res.message, 'success'); setTimeout(function(){location.reload();}, 800); }
        else { showToast(res.message, 'error'); }
    };
    xhr.send('action=unban&user_id=' + userId);
}

function sendUserMail(userId) {
    var subject = prompt('请输入邮件标题：');
    if(!subject) return;
    var content = prompt('请输入邮件内容（支持HTML）：');
    if(!content) return;
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin/api/users.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        showToast(res.message, res.success ? 'success' : 'error');
    };
    xhr.send('action=send_mail&user_id=' + userId + '&subject=' + encodeURIComponent(subject) + '&content=' + encodeURIComponent(content));
}
