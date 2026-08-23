        </div>
    </main>
</div>

<div id="toastContainer" class="toast-container"></div>
<script src="../assets/js/main.js"></script>
<script>
function showToast(msg, type) {
    type = type || 'info';
    var c = document.getElementById('toastContainer');
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = '<span>' + msg + '</span>';
    c.appendChild(t);
    setTimeout(function(){ t.style.opacity='0'; t.style.transform='translateX(40px)'; setTimeout(function(){t.remove();},300); }, 3500);
}
</script>
</body>
</html>
