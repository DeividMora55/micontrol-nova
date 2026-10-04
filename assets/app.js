(() => {
  const data = window.MICONTROL_DATA || {};

  const modal = document.getElementById('movementModal');
  const openButtons = document.querySelectorAll('[data-open-modal]');
  const closeButtons = document.querySelectorAll('[data-close-modal]');

  const openModal = () => {
    if (!modal) return;
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
  };

  const closeModal = () => {
    if (!modal) return;
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  };

  openButtons.forEach(btn => btn.addEventListener('click', openModal));
  closeButtons.forEach(btn => btn.addEventListener('click', closeModal));
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && modal?.classList.contains('show')) closeModal();
  });

  const tipo = document.getElementById('tipoMovimiento');
  const categoria = document.getElementById('categoriaMovimiento');

  function cargarCategorias() {
    if (!tipo || !categoria || !data.categorias) return;
    const seleccion = categoria.dataset.selected || categoria.value;
    categoria.innerHTML = '';

    (data.categorias[tipo.value] || []).forEach(nombre => {
      const option = document.createElement('option');
      option.value = nombre;
      option.textContent = nombre;
      if (nombre === seleccion) option.selected = true;
      categoria.appendChild(option);
    });

    categoria.dataset.selected = '';
  }

  if (tipo && categoria) {
    tipo.addEventListener('change', cargarCategorias);
    cargarCategorias();
  }

  document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', event => {
      if (!window.confirm('¿Deseas eliminar este movimiento?')) {
        event.preventDefault();
      }
    });
  });

  function setupCanvas(canvas) {
    const rect = canvas.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(rect.width * dpr));
    canvas.height = Math.max(1, Math.floor(Number(canvas.getAttribute('height') || 280) * dpr));
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    return { ctx, width: rect.width, height: Number(canvas.getAttribute('height') || 280) };
  }

  function drawTrend() {
    const canvas = document.getElementById('trendChart');
    if (!canvas || !data.trend) return;
    const { ctx, width, height } = setupCanvas(canvas);
    ctx.clearRect(0, 0, width, height);

    const labels = data.trend.labels || [];
    const ingresos = data.trend.ingresos || [];
    const gastos = data.trend.gastos || [];
    const values = [...ingresos, ...gastos, 1];
    const max = Math.max(...values) * 1.18;

    const pad = { l: 48, r: 16, t: 18, b: 38 };
    const cw = width - pad.l - pad.r;
    const ch = height - pad.t - pad.b;

    ctx.strokeStyle = '#17324b';
    ctx.lineWidth = 1;
    ctx.fillStyle = '#6f879f';
    ctx.font = '11px Segoe UI';

    for (let i = 0; i <= 4; i++) {
      const y = pad.t + (ch / 4) * i;
      ctx.beginPath();
      ctx.moveTo(pad.l, y);
      ctx.lineTo(width - pad.r, y);
      ctx.stroke();
      const value = max - (max / 4) * i;
      ctx.fillText('$' + Math.round(value).toLocaleString(), 4, y + 4);
    }

    const xAt = i => labels.length <= 1 ? pad.l + cw / 2 : pad.l + (cw / (labels.length - 1)) * i;
    const yAt = value => pad.t + ch - (Number(value) / max) * ch;

    labels.forEach((label, i) => {
      ctx.fillStyle = '#6f879f';
      ctx.textAlign = 'center';
      ctx.fillText(label, xAt(i), height - 13);
    });

    const drawSeries = (series, color) => {
      ctx.beginPath();
      series.forEach((value, i) => {
        const x = xAt(i), y = yAt(value);
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
      });
      ctx.strokeStyle = color;
      ctx.lineWidth = 2.4;
      ctx.stroke();

      series.forEach((value, i) => {
        const x = xAt(i), y = yAt(value);
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fillStyle = '#07111f';
        ctx.fill();
        ctx.lineWidth = 2;
        ctx.strokeStyle = color;
        ctx.stroke();
      });
    };

    drawSeries(ingresos, '#37d6ff');
    drawSeries(gastos, '#8b7cff');
  }

  function drawCategory() {
    const canvas = document.getElementById('categoryChart');
    if (!canvas || !data.category) return;

    const { ctx, width, height } = setupCanvas(canvas);
    ctx.clearRect(0, 0, width, height);

    const labels = data.category.labels || [];
    const values = data.category.values || [];
    const total = values.reduce((a, b) => a + Number(b), 0);

    if (!values.length || total <= 0) return;

    const colors = ['#37d6ff','#8b7cff','#42e6a4','#ffc857','#ff6685','#5ea8ff','#bd7dff','#58d6c7','#ff9f5a','#7f94ad'];
    const radius = Math.min(92, height * .31, width * .24);
    const cx = Math.min(width * .32, 150);
    const cy = height * .45;
    let start = -Math.PI / 2;

    values.forEach((value, i) => {
      const angle = (Number(value) / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(cx, cy, radius, start, start + angle);
      ctx.strokeStyle = colors[i % colors.length];
      ctx.lineWidth = 24;
      ctx.stroke();
      start += angle;
    });

    ctx.textAlign = 'center';
    ctx.fillStyle = '#e8f2ff';
    ctx.font = '700 22px Segoe UI';
    ctx.fillText('$' + total.toLocaleString(undefined,{maximumFractionDigits:0}), cx, cy - 2);
    ctx.fillStyle = '#7f94ad';
    ctx.font = '11px Segoe UI';
    ctx.fillText('gasto mensual', cx, cy + 18);

    const lx = Math.max(cx + radius + 40, width * .58);
    let ly = 38;
    ctx.textAlign = 'left';

    labels.slice(0, 8).forEach((label, i) => {
      ctx.fillStyle = colors[i % colors.length];
      ctx.fillRect(lx, ly - 8, 10, 10);
      ctx.fillStyle = '#b9cadb';
      ctx.font = '12px Segoe UI';
      ctx.fillText(label, lx + 18, ly);
      ctx.fillStyle = '#7f94ad';
      ctx.fillText('$' + Number(values[i]).toLocaleString(undefined,{maximumFractionDigits:0}), lx + 18, ly + 16);
      ly += 43;
    });
  }

  const redraw = () => {
    drawTrend();
    drawCategory();
  };

  redraw();
  window.addEventListener('resize', () => {
    window.clearTimeout(window.__mcResize);
    window.__mcResize = window.setTimeout(redraw, 120);
  });
})();
