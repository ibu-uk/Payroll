/* PayrollPro – Global JavaScript */
'use strict';

// ── SweetAlert2 Global Configuration ─────────────────────────────────────────
if (typeof Swal !== 'undefined') {
  Swal.mixin({
    customClass: {
      popup: 'swal-custom-popup',
      confirmButton: 'swal-custom-confirm',
      cancelButton: 'swal-custom-cancel'
    },
    buttonsStyling: false
  });
}

// ── Sidebar toggle ─────────────────────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const mainContent = document.getElementById('main-content');
const toggleBtn = document.getElementById('sidebarToggle');
const overlay   = document.createElement('div');
overlay.className = 'sidebar-overlay';
document.body.appendChild(overlay);

if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
      sidebar.classList.toggle('open');
      overlay.classList.toggle('active');
    } else {
      sidebar.classList.toggle('sidebar-collapsed');
      localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('sidebar-collapsed'));
    }
  });
}

overlay.addEventListener('click', () => {
  sidebar.classList.remove('open');
  overlay.classList.remove('active');
});

// Restore sidebar state on desktop
if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === 'true') {
  sidebar?.classList.add('sidebar-collapsed');
}

// ── Select2 init ───────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (window.jQuery && $.fn.select2) {
    $('.select2').select2({
      theme: 'bootstrap-5',
      width: '100%',
      placeholder: function() { return $(this).data('placeholder') || 'Select...'; }
    });
  }

  // ── DataTables default init ──────────────────────────────────────────────
  if (window.jQuery && $.fn.DataTable) {
    $('.datatable').DataTable({
      responsive: true,
      pageLength: 25,
      language: {
        search: '🔍',
        lengthMenu: '_MENU_ / page',
        info: 'Showing _START_–_END_ of _TOTAL_',
      },
      dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
           "<'row'<'col-sm-12'tr>>" +
           "<'row'<'col-sm-5'i><'col-sm-7'p>>",
    });
  }

  // ── Auto-dismiss flash alerts ────────────────────────────────────────────
  setTimeout(() => {
    document.querySelectorAll('.alert').forEach(el => {
      const bsAlert = window.bootstrap?.Alert?.getOrCreateInstance(el);
      if (bsAlert) bsAlert.close();
    });
  }, 4000);

  // ── Tooltips ─────────────────────────────────────────────────────────────
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
  });

  // ── Form validation ───────────────────────────────────────────────────────
  document.querySelectorAll('form.needs-validation').forEach(form => {
    form.addEventListener('submit', e => {
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });

  // ── Confirm delete buttons ────────────────────────────────────────────────
  document.querySelectorAll('.btn-delete').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      const form = btn.closest('form') || document.getElementById(btn.dataset.form);
      Swal.fire({
        title: btn.dataset.title || 'Are you sure?',
        text: btn.dataset.text || 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: btn.dataset.confirm || 'Delete',
        cancelButtonText: 'Cancel',
      }).then(r => { if (r.isConfirmed && form) form.submit(); });
    });
  });

  // ── Print page ────────────────────────────────────────────────────────────
  document.querySelectorAll('.btn-print').forEach(btn => {
    btn.addEventListener('click', () => window.print());
  });

  // ── Currency formatter ────────────────────────────────────────────────────
  document.querySelectorAll('input[data-currency]').forEach(input => {
    input.addEventListener('blur', () => {
      const v = parseFloat(input.value) || 0;
      input.value = v.toFixed(3);
    });
  });

  // ── Live salary calculator in employee form ───────────────────────────────
  const basicInput = document.querySelector('[name="basic_salary"]');
  if (basicInput) {
    basicInput.addEventListener('input', updateSalarySummary);
    document.querySelectorAll('[name="allowance_amount[]"]').forEach(el => {
      el.addEventListener('input', updateSalarySummary);
    });
  }

  function updateSalarySummary() {
    const basic = parseFloat(document.querySelector('[name="basic_salary"]')?.value) || 0;
    const allowanceInputs = document.querySelectorAll('[name="allowance_amount[]"]');
    let totalAllow = 0;
    allowanceInputs.forEach(el => { totalAllow += parseFloat(el.value) || 0; });
    const gross = basic + totalAllow;

    const summaryEl = document.getElementById('salarySummary');
    if (summaryEl) {
      summaryEl.innerHTML = `
        <small class="text-muted">Basic: <strong>${basic.toFixed(3)}</strong> +
        Allowances: <strong>${totalAllow.toFixed(3)}</strong> =
        Gross: <strong class="text-success">${gross.toFixed(3)}</strong></small>`;
    }
  }

  // ── Attendance cell color sync ────────────────────────────────────────────
  document.querySelectorAll('.att-select').forEach(sel => {
    const colors = {
      present: 'rgba(16,185,129,.15)',
      absent:  'rgba(239,68,68,.15)',
      late:    'rgba(245,158,11,.15)',
      half_day:'rgba(6,182,212,.15)',
      leave:   'rgba(59,130,246,.15)',
    };
    const td = sel.closest('td');
    if (sel.value && colors[sel.value]) td.style.backgroundColor = colors[sel.value];
  });

  // ── Date range auto-calc days ─────────────────────────────────────────────
  const startDate = document.querySelector('[name="start_date"]');
  const endDate   = document.querySelector('[name="end_date"]');
  const daysDisplay = document.getElementById('leaveDays');
  if (startDate && endDate && daysDisplay) {
    function calcDays() {
      const s = new Date(startDate.value), e = new Date(endDate.value);
      if (s && e && e >= s) {
        const diff = Math.round((e - s) / 86400000) + 1;
        daysDisplay.textContent = diff + ' day(s)';
      }
    }
    startDate.addEventListener('change', calcDays);
    endDate.addEventListener('change', calcDays);
  }
});

// ── Toast helper ──────────────────────────────────────────────────────────
window.showToast = function(msg, type = 'success') {
  const toastEl = document.createElement('div');
  toastEl.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
  toastEl.setAttribute('role','alert');
  toastEl.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  document.body.appendChild(toastEl);
  const toast = new bootstrap.Toast(toastEl, {delay: 3000});
  toast.show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
};

// ── AJAX helper ───────────────────────────────────────────────────────────
window.apiGet = function(endpoint, params = {}) {
  const qs = new URLSearchParams({page:'api', endpoint, ...params});
  return fetch(`index.php?${qs}`).then(r => r.json());
};
