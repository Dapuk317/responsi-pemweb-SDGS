/**
 * GREENCYCLE - VANILLA JS DASHBOARD INTERACTION ENGINE
 * Pure Vanilla JavaScript (No frameworks, No dependencies)
 */

document.addEventListener("DOMContentLoaded", function () {
    initSidebar();
    initClock();
    initPasswordToggle();
    initTabs();
    initTableSearch();
    initAnimatedCounters();
    initAlerts();
    initModals();
    initRolePills();
});

/* ==========================================================================
   1. SIDEBAR TOGGLE & BACKDROP
   ========================================================================== */
function initSidebar() {
    const sidebar = document.getElementById("gcSidebar");
    const menuToggle = document.getElementById("gcMenuToggle");
    const sidebarClose = document.getElementById("gcSidebarClose");
    const backdrop = document.getElementById("gcSidebarBackdrop");

    if (!sidebar) return;

    function openSidebar() {
        sidebar.classList.add("open");
        if (backdrop) backdrop.classList.add("open");
        document.body.style.overflow = "hidden";
    }

    function closeSidebar() {
        sidebar.classList.remove("open");
        if (backdrop) backdrop.classList.remove("open");
        document.body.style.overflow = "";
    }

    if (menuToggle) menuToggle.addEventListener("click", openSidebar);
    if (sidebarClose) sidebarClose.addEventListener("click", closeSidebar);
    if (backdrop) backdrop.addEventListener("click", closeSidebar);
}

/* ==========================================================================
   2. REALTIME TOPBAR CLOCK
   ========================================================================== */
function initClock() {
    const clockElement = document.getElementById("gcLiveClock");
    if (!clockElement) return;

    function update() {
        const now = new Date();
        const options = {
            day: "numeric",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hour12: false
        };
        try {
            clockElement.textContent = now.toLocaleDateString("id-ID", options);
        } catch (e) {
            clockElement.textContent = now.toTimeString().split(" ")[0];
        }
    }

    update();
    setInterval(update, 1000);
}

/* ==========================================================================
   3. PASSWORD VISIBILITY TOGGLER
   ========================================================================== */
function initPasswordToggle() {
    const toggleButtons = document.querySelectorAll(".gc-input-toggle-pw");

    toggleButtons.forEach(btn => {
        btn.addEventListener("click", function () {
            const targetId = this.getAttribute("data-target");
            const input = targetId ? document.getElementById(targetId) : this.closest(".gc-input-wrapper").querySelector("input");

            if (!input) return;

            const isPassword = input.type === "password";
            input.type = isPassword ? "text" : "password";

            // Update SVG icon
            if (isPassword) {
                this.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                `;
                this.setAttribute("title", "Sembunyikan password");
            } else {
                this.innerHTML = `
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                `;
                this.setAttribute("title", "Tampilkan password");
            }
        });
    });
}

/* ==========================================================================
   4. INTERACTIVE TABS SYSTEM
   ========================================================================== */
function initTabs() {
    const tabHeaders = document.querySelectorAll(".gc-tabs-header");

    tabHeaders.forEach(header => {
        const buttons = header.querySelectorAll(".gc-tab-btn");
        const container = header.parentElement;

        buttons.forEach(btn => {
            btn.addEventListener("click", function () {
                const targetId = this.getAttribute("data-tab");
                if (!targetId) return;

                // Remove active from all buttons in this header
                buttons.forEach(b => b.classList.remove("active"));
                this.classList.add("active");

                // Show target pane
                const panes = container.querySelectorAll(".gc-tab-pane");
                panes.forEach(pane => {
                    if (pane.id === targetId) {
                        pane.classList.add("active");
                    } else {
                        pane.classList.remove("active");
                    }
                });
            });
        });
    });
}

/* ==========================================================================
   5. LIVE TABLE SEARCH & STATUS FILTER (INSTANT CLIENT-SIDE FILTER)
   ========================================================================== */
function initTableSearch() {
    const searchInputs = document.querySelectorAll("[data-table-search]");

    searchInputs.forEach(input => {
        const targetSelector = input.getAttribute("data-table-search");
        const table = document.querySelector(targetSelector);
        if (!table) return;

        const rows = table.querySelectorAll("tbody tr");

        input.addEventListener("input", function () {
            applyFilter();
        });

        // Also check if there's a connected status filter dropdown
        const statusSelect = document.querySelector(`[data-table-status="${targetSelector}"]`);
        if (statusSelect) {
            statusSelect.addEventListener("change", function () {
                applyFilter();
            });
        }

        function applyFilter() {
            const query = input.value.toLowerCase().trim();
            const statusFilter = statusSelect ? statusSelect.value.toLowerCase() : "";

            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowStatus = (row.getAttribute("data-status") || "").toLowerCase();

                const matchesQuery = !query || text.includes(query);
                const matchesStatus = !statusFilter || statusFilter === "all" || rowStatus === statusFilter;

                if (matchesQuery && matchesStatus) {
                    row.style.display = "";
                    visibleCount++;
                } else {
                    row.style.display = "none";
                }
            });

            // Handle empty state row
            let emptyRow = table.querySelector(".gc-no-results-row");
            if (visibleCount === 0) {
                if (!emptyRow) {
                    const colCount = table.querySelectorAll("thead th").length || 6;
                    emptyRow = document.createElement("tr");
                    emptyRow.className = "gc-no-results-row";
                    emptyRow.innerHTML = `<td colspan="${colCount}" style="text-align: center; padding: 30px; color: var(--gc-text-muted);">Tidak ada data yang cocok dengan pencarian.</td>`;
                    table.querySelector("tbody").appendChild(emptyRow);
                }
                emptyRow.style.display = "";
            } else if (emptyRow) {
                emptyRow.style.display = "none";
            }
        }
    });
}

/* ==========================================================================
   6. ANIMATED NUMBER COUNTERS
   ========================================================================== */
function initAnimatedCounters() {
    const counters = document.querySelectorAll(".gc-count-up");

    counters.forEach(counter => {
        const target = parseFloat(counter.getAttribute("data-count")) || 0;
        const isCurrency = counter.getAttribute("data-currency") === "idr";
        const duration = 1000;
        const startTime = performance.now();

        function updateCount(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out cubic
            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = target * easeOut;

            if (isCurrency) {
                counter.textContent = "Rp " + Math.floor(current).toLocaleString("id-ID");
            } else {
                counter.textContent = Math.floor(current).toLocaleString("id-ID");
            }

            if (progress < 1) {
                requestAnimationFrame(updateCount);
            } else {
                if (isCurrency) {
                    counter.textContent = "Rp " + Math.round(target).toLocaleString("id-ID");
                } else {
                    counter.textContent = Math.round(target).toLocaleString("id-ID");
                }
            }
        }

        requestAnimationFrame(updateCount);
    });
}

/* ==========================================================================
   7. DISMISSIBLE ALERTS
   ========================================================================== */
function initAlerts() {
    const alertCloses = document.querySelectorAll(".gc-alert-close");

    alertCloses.forEach(btn => {
        btn.addEventListener("click", function () {
            const alert = this.closest(".gc-alert");
            if (alert) {
                alert.style.transition = "opacity 0.25s ease, transform 0.25s ease";
                alert.style.opacity = "0";
                alert.style.transform = "translateY(-6px)";
                setTimeout(() => alert.remove(), 250);
            }
        });
    });
}

/* ==========================================================================
   8. MODAL DIALOG CONTROLLER
   ========================================================================== */
function initModals() {
    const openBtns = document.querySelectorAll("[data-modal-open]");
    const closeBtns = document.querySelectorAll("[data-modal-close]");

    openBtns.forEach(btn => {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            const modalId = this.getAttribute("data-modal-open");
            const modal = document.querySelector(modalId);
            if (modal) {
                modal.classList.add("open");
                document.body.style.overflow = "hidden";
            }
        });
    });

    closeBtns.forEach(btn => {
        btn.addEventListener("click", function () {
            const modal = this.closest(".gc-modal-backdrop");
            if (modal) {
                modal.classList.remove("open");
                document.body.style.overflow = "";
            }
        });
    });

    // Close when clicking outside of modal
    document.querySelectorAll(".gc-modal-backdrop").forEach(backdrop => {
        backdrop.addEventListener("click", function (e) {
            if (e.target === this) {
                this.classList.remove("open");
                document.body.style.overflow = "";
            }
        });
    });
}

/* ==========================================================================
   9. LOGIN ROLE HELPER PILLS
   ========================================================================== */
function initRolePills() {
    const rolePills = document.querySelectorAll(".gc-role-pill");
    const emailInput = document.getElementById("loginEmail");
    const roleHelperDesc = document.getElementById("roleHelperDesc");

    const roleInfo = {
        customer: "Role Customer: Menjelajahi katalog daur ulang, melihat order tracker, dan keranjang belanja.",
        seller: "Role Seller: Mengelola produk daur ulang toko, stok, dan melihat pesanan masuk.",
        admin: "Role Admin: Mengelola seluruh pengguna, validasi toko, kategori, dan memantau analitik platform."
    };

    rolePills.forEach(pill => {
        pill.addEventListener("click", function () {
            rolePills.forEach(p => p.classList.remove("active"));
            this.classList.add("active");

            const role = this.getAttribute("data-role");
            if (roleHelperDesc && roleInfo[role]) {
                roleHelperDesc.textContent = roleInfo[role];
                roleHelperDesc.style.display = "block";
            }
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }
        });
    });
}
