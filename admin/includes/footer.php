  </div><!-- end .main-content -->
</div><!-- end .main-wrapper -->

<script>
// Toggle sidebar on mobile / collapse
function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const wrapper = document.getElementById('mainWrapper');
  sidebar.classList.toggle('collapsed');
  wrapper.classList.toggle('sidebar-collapsed');
}

// Confirm delete dialogs
document.querySelectorAll('.btn-delete').forEach(btn => {
  btn.addEventListener('click', e => {
    if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
      e.preventDefault();
    }
  });
});

// Auto-dismiss alerts after 5 seconds
setTimeout(() => {
  document.querySelectorAll('.alert').forEach(a => {
    a.style.transition = 'opacity 0.5s';
    a.style.opacity = '0';
    setTimeout(() => a.remove(), 500);
  });
}, 5000);
</script>

<style>
/* Sidebar collapse */
.sidebar.collapsed { transform: translateX(-260px); }
.main-wrapper.sidebar-collapsed { margin-left: 0; }

/* Table action buttons */
.action-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
.action-btns .btn { padding: 0.3rem 0.7rem; font-size: 0.78rem; }

/* Modal */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.5); z-index: 1000;
  align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff; border-radius: 16px;
  padding: 2rem; width: 90%; max-width: 560px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  animation: fadeInUp 0.3s ease;
}
.modal-header {
  display: flex; align-items: center;
  justify-content: space-between; margin-bottom: 1.5rem;
}
.modal-close {
  background: none; border: none; font-size: 1.2rem;
  cursor: pointer; color: var(--text-light);
  padding: 0.25rem; border-radius: 4px;
}
.modal-close:hover { color: var(--danger); }
</style>
</body>
</html>
