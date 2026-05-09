/* Viana Clips — listing app */
(function () {
  const PAGE_SIZE = 12;
  const state = { period: "all", sort: "recent", date: "", page: 1 };

  const grid = document.getElementById("clip-grid");
  const paginationEl = document.getElementById("pagination");

  function withinPeriod(clip) {
    if (state.period === "all") return true;
    const created = new Date(clip.createdAt);
    const diffH = (window.NOW_REF - created) / 3600000;
    if (state.period === "1h") return diffH <= 1;
    if (state.period === "6h") return diffH <= 6;
    if (state.period === "12h") return diffH <= 12;
    return true;
  }

  function filtered() {
    let list = window.CLIPS.filter((c) => window.timeRemaining(c.expiresAt).minutes > 0);
    list = list.filter(withinPeriod);
    if (state.date) list = list.filter((c) => c.date === state.date);
    if (state.sort === "recent") list = list.slice().sort((a, b) => (a.createdAt < b.createdAt ? 1 : -1));
    else if (state.sort === "expiring") list = list.slice().sort((a, b) => (a.expiresAt < b.expiresAt ? -1 : 1));
    return list;
  }

  function expBadgeHTML(rem) {
    let cls = "";
    if (rem.urgency >= 0.92) cls = "urgent";
    else if (rem.urgency >= 0.75) cls = "warn";
    return `<span class="exp-badge ${cls}"><span class="exp-dot"></span>${rem.text}</span>`;
  }

  function clipCard(clip, index) {
    const url = `player.html?id=${encodeURIComponent(clip.id)}`;
    const rem = window.timeRemaining(clip.expiresAt);
    const a = document.createElement("a");
    a.href = url;
    a.className = "clip";
    a.setAttribute("data-id", clip.id);
    a.innerHTML = `
      <div class="thumb-wrap">
        <div class="thumb ${clip.thumb}">
          <div class="thumb-overlay"></div>
          <div class="play-pill"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></div>
          <div class="thumb-time">${clip.time}</div>
          <div class="thumb-br">${clip.duration}</div>
        </div>
      </div>
      <div class="clip-meta-bottom">
        <div class="clip-num-row">
          <div class="clip-num">${clip.id.toUpperCase()}</div>
          ${expBadgeHTML(rem)}
        </div>
        <h3 class="clip-title">${clip.title}</h3>
        <div class="clip-meta">
          <span>${window.relativeTime(clip.createdAt)}</span>
          <span class="dot"></span>
          <span>${window.formatDate(clip.date)}</span>
          <span class="dot"></span>
          <span>${clip.duration}</span>
        </div>
      </div>
    `;
    return a;
  }

  function renderPagination(total) {
    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    paginationEl.innerHTML = "";
    if (pages <= 1) return;
    const mk = (label, value, opts = {}) => {
      const b = document.createElement("button");
      b.className = `page-btn ${opts.arrow ? "arrow" : ""} ${opts.active ? "active" : ""}`;
      b.textContent = label;
      if (opts.disabled) b.disabled = true;
      b.addEventListener("click", () => {
        state.page = value;
        render();
        window.scrollTo({ top: 0, behavior: "smooth" });
      });
      return b;
    };
    paginationEl.appendChild(mk("← Anterior", Math.max(1, state.page - 1), { arrow: true, disabled: state.page === 1 }));
    for (let p = 1; p <= pages; p++) paginationEl.appendChild(mk(String(p), p, { active: p === state.page }));
    paginationEl.appendChild(mk("Próximo →", Math.min(pages, state.page + 1), { arrow: true, disabled: state.page === pages }));
  }

  function render() {
    const list = filtered();
    const start = (state.page - 1) * PAGE_SIZE;
    const slice = list.slice(start, start + PAGE_SIZE);
    grid.innerHTML = "";
    if (slice.length === 0) {
      grid.innerHTML = `<div class="empty"><div class="display">Nenhum clipe ativo</div><div>Os lances ficam disponíveis por 24h após a gravação.</div></div>`;
    } else {
      slice.forEach((c, i) => grid.appendChild(clipCard(c, start + i)));
    }
    renderPagination(list.length);
  }

  function bindSeg(id, key) {
    const seg = document.getElementById(id);
    seg.addEventListener("click", (e) => {
      const btn = e.target.closest("button");
      if (!btn) return;
      seg.querySelectorAll("button").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      state[key] = btn.getAttribute(`data-${key}`);
      state.page = 1;
      render();
    });
  }
  bindSeg("period-seg", "period");
  bindSeg("sort-seg", "sort");

  document.getElementById("date-input").addEventListener("change", (e) => {
    state.date = e.target.value;
    state.page = 1;
    render();
  });

  render();
})();
