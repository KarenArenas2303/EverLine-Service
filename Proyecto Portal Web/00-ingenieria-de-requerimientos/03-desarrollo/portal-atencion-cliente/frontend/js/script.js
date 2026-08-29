// =========================================================
// Configuración: ruta del backend
// Si el proyecto está en htdocs/portal-atencion-cliente/,
// esta ruta relativa ya funciona sin cambios.
// =========================================================
const API_BASE = '../backend/api';

// ---------- Reloj en vivo (Bogotá) ----------
function updateClock(){
  const now = new Date();
  const timeFmt = new Intl.DateTimeFormat('es-CO', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'America/Bogota' });
  const dateFmt = new Intl.DateTimeFormat('es-CO', { weekday:'long', day:'numeric', month:'long', timeZone:'America/Bogota' });
  document.getElementById('clock').textContent = timeFmt.format(now);
  document.getElementById('clock-date').textContent = dateFmt.format(now);
  document.getElementById('tower-updated').textContent = timeFmt.format(now);
}
updateClock();
setInterval(updateClock, 1000);

// =========================================================
// Crear solicitud -> POST /backend/api/crear_solicitud.php
// =========================================================
document.getElementById('request-form').addEventListener('submit', async function (e) {
  e.preventDefault();
  const btn = e.target.querySelector('.submit-btn');
  const box = document.getElementById('confirm-box');
  btn.textContent = 'Enviando...';
  btn.disabled = true;
  box.classList.remove('show', 'error');

  const payload = {
    nombre:      document.getElementById('nombre').value,
    email:       document.getElementById('email').value,
    telefono:    document.getElementById('telefono').value,
    tipo:        document.getElementById('tipo').value,
    prioridad:   document.getElementById('prioridad').value,
    descripcion: document.getElementById('descripcion').value
  };

  try {
    const res = await fetch(`${API_BASE}/crear_solicitud.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!res.ok) throw new Error(data.error || 'No se pudo enviar la solicitud');

    // Respuesta inmediata con código de caso
    box.innerHTML = 'Solicitud recibida. Tu número de caso es <b>' + data.codigo_caso + '</b>. Guárdalo para hacer seguimiento.';
    box.innerHTML += '<div class="ia-loading" style="margin-top:10px;padding:10px;background:#fff3e0;border:1px solid #ffe0b2;border-radius:8px;font-size:13px;color:#e65100;">🤖 Generando análisis con IA... <span class="ia-spinner">⏳</span></div>';
    
    // Disparar generación de IA (async, no await)
    fetch(`${API_BASE}/generar_ia.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ codigo: data.codigo_caso })
    }).catch(() => {}); // Silenciar errores de red
    
    // Iniciar polling para análisis de IA
    pollIAAnalysis(data.codigo_caso, box);
    
    box.classList.add('show');
    e.target.reset();
    loadRecent();
    loadAdminReport();
  } catch (err) {
    box.innerHTML = err.message || 'Ocurrió un error al enviar tu solicitud. Intenta nuevamente.';
    box.classList.add('show', 'error');
  } finally {
    btn.textContent = 'Enviar solicitud';
    btn.disabled = false;
  }
});

let iaPollInterval = null;
async function pollIAAnalysis(codigo, box) {
  if (iaPollInterval) clearInterval(iaPollInterval);
  
  let attempts = 0;
  const maxAttempts = 60; // 60 intentos x 5s = 5 min máx
  
  iaPollInterval = setInterval(async () => {
    attempts++;
    try {
      const res = await fetch(`${API_BASE}/ia_analisis.php?codigo=${encodeURIComponent(codigo)}`);
      const data = await res.json();
      
      if (!res.ok) throw new Error(data.error || 'Error consultando análisis');
      
      if (!data.pending && data.analysis) {
        // Análisis listo
        clearInterval(iaPollInterval);
        showIAAnalysis(box, data.analysis);
      } else if (data.analysis && data.analysis.error) {
        // Error en IA
        clearInterval(iaPollInterval);
        showIAError(box, data.analysis.error);
      }
      // Si pending: seguir esperando
      
    } catch (err) {
      // Error de red, seguir reintentando
    }
    
    if (attempts >= maxAttempts) {
      clearInterval(iaPollInterval);
      const loading = box.querySelector('.ia-loading');
      if (loading) loading.innerHTML = '⚠️ El análisis tarda más de lo esperado. Puedes consultarlo luego en la sección de rastreo.';
    }
  }, 5000); // Cada 5 segundos
}

function showIAAnalysis(box, analysis) {
  const loading = box.querySelector('.ia-loading');
  if (loading) loading.remove();
  
  const html = `
    <details class="ia-analysis" open style="margin-top:12px;padding:12px;background:#f0f7ff;border:1px solid #d0e3f0;border-radius:8px;">
      <summary style="cursor:pointer;font-weight:600;color:#1a4a7a;font-family:var(--font-display);">🤖 Análisis de IA completado</summary>
      <div style="margin-top:10px;font-size:13.5px;line-height:1.6;">
        <p><strong>Resumen:</strong> ${analysis.resumen || 'N/A'}</p>
        <p><strong>Categoría sugerida:</strong> ${analysis.categoria_sugerida || 'N/A'}</p>
        <p><strong>Prioridad sugerida:</strong> ${analysis.prioridad_sugerida || 'N/A'}</p>
        <p><strong>Tiempo estimado:</strong> ${analysis.tiempo_estimado || 'N/A'}</p>
        <p><strong>Escalamiento:</strong> ${analysis.escalamiento || 'N/A'}</p>
        ${analysis.solucion_inmediata?.length ? '<p><strong>Solución inmediata:</strong><ul style="margin:4px 0;padding-left:18px;">' + analysis.solucion_inmediata.map(s => '<li>' + s + '</li>').join('') + '</ul></p>' : ''}
        ${analysis.acciones_equipo?.length ? '<p><strong>Acciones del equipo:</strong><ul style="margin:4px 0;padding-left:18px;">' + analysis.acciones_equipo.map(s => '<li>' + s + '</li>').join('') + '</ul></p>' : ''}
        ${analysis.conocimiento_base?.length ? '<p><strong>Base de conocimiento:</strong><ul style="margin:4px 0;padding-left:18px;">' + analysis.conocimiento_base.map(s => '<li>' + s + '</li>').join('') + '</ul></p>' : ''}
      </div>
    </details>
  `;
  box.innerHTML += html;
}

function showIAError(box, error) {
  const loading = box.querySelector('.ia-loading');
  if (loading) loading.innerHTML = '❌ Error generando análisis: ' + error + '. Puedes reintentar desde la sección de rastreo.';
  loading.style.background = '#fbe9e7';
  loading.style.borderColor = '#ffccbc';
  loading.style.color = '#c62828';
}

// Convierte el estado (ej. "En proceso") en una clase CSS (ej. "en-proceso")
function estadoClass(estado){
  return (estado || '').toLowerCase().trim().replace(/\s+/g, '-');
}
// =========================================================
// Rastrear solicitud -> GET /backend/api/rastrear_solicitud.php
// =========================================================
async function trackTicket(){
  const input = document.getElementById('track-input');
  const result = document.getElementById('track-result');
  const id = input.value.trim().toUpperCase();
  if (!id){ result.innerHTML = ''; return; }

  result.innerHTML = 'Buscando...';
  try {
    const res = await fetch(`${API_BASE}/rastrear_solicitud.php?codigo=${encodeURIComponent(id)}`);
    const t = await res.json();
    if (!res.ok) throw new Error(t.error || 'No encontramos ese código de caso.');

   result.innerHTML =
  '<span class="ticket-pill ' + estadoClass(t.estado) + '">' + t.estado + '</span> &nbsp;' +
      new Date(t.fecha_creacion).toLocaleDateString('es-CO');
  } catch (err) {
    result.innerHTML = err.message;
  }
}
document.getElementById('track-input').addEventListener('keydown', function (e) {
  if (e.key === 'Enter') trackTicket();
});

// =========================================================
// Últimas solicitudes -> GET /backend/api/solicitudes_recientes.php
// =========================================================
async function loadRecent(){
  const list = document.getElementById('recent-list');
  try {
    const res = await fetch(`${API_BASE}/solicitudes_recientes.php`);
    const items = await res.json();
    if (!Array.isArray(items) || !items.length){ list.innerHTML = ''; return; }

    list.innerHTML = items.map(t =>
  '<div class="recent-item"><span class="mono">' + t.codigo_caso + '</span><span class="ticket-pill ' + estadoClass(t.estado) + '">' + t.estado + '</span></div>'
).join('');
  } catch (err) {
    list.innerHTML = '';
  }
}
loadRecent();

// =========================================================
// Panel administrativo -> GET /backend/api/reporte_mensual.php
// =========================================================
async function loadAdminReport(){
  const barsEl = document.getElementById('bars-chart');
  const catEl  = document.getElementById('cat-chart');

  try {
    const res = await fetch(`${API_BASE}/reporte_mensual.php`);
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'No fue posible cargar el reporte');

    // Gráfico de barras por mes
    const porMes = data.por_mes || [];
    if (!porMes.length){
      barsEl.innerHTML = '<span style="color:#9FC3CE; font-size:13px;">Aún no hay datos suficientes.</span>';
    } else {
      const max = Math.max(...porMes.map(m => Number(m.total)), 1);
      barsEl.innerHTML = porMes.map(m => {
        const h = Math.max(8, Math.round((Number(m.total) / max) * 100));
        return '<div class="bar-col"><div class="bar" style="height:' + h + '%"></div><span>' + m.mes.toUpperCase() + '</span></div>';
      }).join('');
    }

    // Distribución por tipo
    const porTipo = data.por_tipo || [];
    if (!porTipo.length){
      catEl.innerHTML = '<span style="color:#9FC3CE; font-size:13px;">Aún no hay datos suficientes.</span>';
    } else {
      const maxTipo = Math.max(...porTipo.map(c => Number(c.total)), 1);
      catEl.innerHTML = porTipo.map(c => {
        const w = Math.max(6, Math.round((Number(c.total) / maxTipo) * 100));
        return '<div class="cat-row"><span>' + c.tipo + '</span><div class="cat-track"><div class="cat-fill" style="width:' + w + '%"></div></div></div>';
      }).join('');
    }
  } catch (err) {
    barsEl.innerHTML = '<span style="color:#9FC3CE; font-size:13px;">No fue posible cargar el reporte.</span>';
    catEl.innerHTML = '';
  }
}
loadAdminReport();