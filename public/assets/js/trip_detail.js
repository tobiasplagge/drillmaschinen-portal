/* ─── ITD Landmaschinen Manager – Fahrt Detailansicht ───────────────────── */
(function () {
  'use strict';

  // ─── Leaflet Karte ──────────────────────────────────────────────────────────
  const mapEl = document.getElementById('map');
  if (!mapEl || typeof GPS_POINTS === 'undefined') return;

  const map = L.map('map', { zoomControl: true });

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);

  // ─── Fahrspur ─────────────────────────────────────────────────────────────
  if (GPS_POINTS.length > 0) {
    const workingCoords  = [];
    const headlandCoords = [];

    GPS_POINTS.forEach(([lat, lon, working]) => {
      if (working) workingCoords.push([lat, lon]);
      else         headlandCoords.push([lat, lon]);
    });

    if (workingCoords.length > 0) {
      L.polyline(workingCoords, {
        color: '#2d5a27',
        weight: 2.5,
        opacity: 0.85,
      }).addTo(map);
    }

    if (headlandCoords.length > 0) {
      L.polyline(headlandCoords, {
        color: '#7ab070',
        weight: 2,
        opacity: 0.6,
        dashArray: '5, 5',
      }).addTo(map);
    }

    // Start-Marker
    const startPt = GPS_POINTS[0];
    L.circleMarker([startPt[0], startPt[1]], {
      radius: 8, color: '#fff', weight: 2, fillColor: '#27ae60', fillOpacity: 1,
    }).bindTooltip('Start', { permanent: false }).addTo(map);

    // End-Marker
    const endPt = GPS_POINTS[GPS_POINTS.length - 1];
    L.circleMarker([endPt[0], endPt[1]], {
      radius: 8, color: '#fff', weight: 2, fillColor: '#2980b9', fillOpacity: 1,
    }).bindTooltip('Ende', { permanent: false }).addTo(map);

    // Karte auf Track zentrieren
    const allCoords = GPS_POINTS.map(([lat, lon]) => [lat, lon]);
    map.fitBounds(L.latLngBounds(allCoords), { padding: [20, 20] });
  } else {
    map.setView([51.0, 10.0], 6);
  }

  // ─── Ereignis-Marker ──────────────────────────────────────────────────────
  if (typeof EVENTS !== 'undefined') {
    EVENTS.forEach(ev => {
      if (!ev.lat || !ev.lon) return;

      const colors = {
        fault:   { fill: '#c0392b', border: '#fff' },
        blower:  { fill: '#d4870a', border: '#fff' },
        warning: { fill: '#e67e22', border: '#fff' },
        info:    { fill: '#2980b9', border: '#fff' },
      };
      const c = colors[ev.type] || colors.info;

      const marker = L.circleMarker([ev.lat, ev.lon], {
        radius: 8, color: c.border, weight: 2.5,
        fillColor: c.fill, fillOpacity: 1,
      });

      const icons = { fault: '⚠️', blower: '💨', warning: '⚡', info: 'ℹ️' };
      marker.bindPopup(
        `<div style="font-size:13px; min-width:160px;">
          <strong>${icons[ev.type] || ''} ${escHtml(ev.type === 'fault' ? 'Störung' : ev.type === 'blower' ? 'Gebläse' : 'Ereignis')}</strong><br>
          ${escHtml(ev.message)}<br>
          <span style="color:#888; font-size:11px;">${escHtml(ev.time)} Uhr</span>
        </div>`,
        { maxWidth: 240 }
      );

      marker.addTo(map);
    });
  }

  // ─── Legende ──────────────────────────────────────────────────────────────
  const legend = L.control({ position: 'bottomright' });
  legend.onAdd = function () {
    const div = L.DomUtil.create('div');
    div.style.cssText = 'background:rgba(255,255,255,.92); padding:10px 14px; border-radius:8px; font-size:12px; box-shadow:0 2px 6px rgba(0,0,0,.15);';
    div.innerHTML = `
      <div style="font-weight:700; margin-bottom:6px; color:#333;">Legende</div>
      <div><span style="display:inline-block;width:12px;height:3px;background:#27ae60;border-radius:2px;margin-right:6px;vertical-align:middle;"></span>Startpunkt</div>
      <div><span style="display:inline-block;width:12px;height:3px;background:#2980b9;border-radius:2px;margin-right:6px;vertical-align:middle;"></span>Endpunkt</div>
      <div><span style="display:inline-block;width:12px;height:3px;background:#2d5a27;border-radius:2px;margin-right:6px;vertical-align:middle;"></span>Arbeitsspur</div>
      <div><span style="display:inline-block;width:12px;height:3px;background:#7ab070;border-radius:2px;margin-right:6px;vertical-align:middle;border-top:2px dashed #7ab070;"></span>Wendefahrt</div>
      <div><span style="display:inline-block;width:12px;height:12px;background:#c0392b;border-radius:50%;margin-right:6px;vertical-align:middle;"></span>Störung</div>
      <div><span style="display:inline-block;width:12px;height:12px;background:#d4870a;border-radius:50%;margin-right:6px;vertical-align:middle;"></span>Gebläse</div>`;
    return div;
  };
  legend.addTo(map);

  // ─── Chart.js Diagramme ───────────────────────────────────────────────────
  const chartTabBtn = document.getElementById('chartTabBtn');
  let chartsBuilt = false;

  function buildCharts() {
    if (chartsBuilt || typeof CHART_DATA === 'undefined' || CHART_DATA.length === 0) return;
    chartsBuilt = true;

    const labels = CHART_DATA.map(d => d.t);
    const defaults = {
      tension: 0.3,
      pointRadius: 0,
      borderWidth: 2,
    };

    // Geschwindigkeit
    const ctxSpeed = document.getElementById('chartSpeed');
    if (ctxSpeed) {
      new Chart(ctxSpeed, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            ...defaults,
            label: 'Geschwindigkeit (km/h)',
            data: CHART_DATA.map(d => d.s),
            borderColor: '#2d5a27',
            backgroundColor: 'rgba(45,90,39,.08)',
            fill: true,
          }],
        },
        options: chartOptions('km/h'),
      });
    }

    // Gebläsedruck
    const ctxBlower = document.getElementById('chartBlower');
    if (ctxBlower) {
      new Chart(ctxBlower, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            ...defaults,
            label: 'Gebläsedruck (mbar)',
            data: CHART_DATA.map(d => d.b),
            borderColor: '#d4870a',
            backgroundColor: 'rgba(212,135,10,.08)',
            fill: true,
          }],
        },
        options: chartOptions('mbar'),
      });
    }

    // Saatmenge
    const ctxSeedRate = document.getElementById('chartSeedRate');
    if (ctxSeedRate) {
      new Chart(ctxSeedRate, {
        type: 'line',
        data: {
          labels,
          datasets: [{
            ...defaults,
            label: 'Saatmenge (kg/ha)',
            data: CHART_DATA.map(d => d.r),
            borderColor: '#2980b9',
            backgroundColor: 'rgba(41,128,185,.08)',
            fill: true,
          }],
        },
        options: chartOptions('kg/ha'),
      });
    }
  }

  function chartOptions(unit) {
    return {
      responsive: true,
      animation: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => `${ctx.parsed.y} ${unit}`,
          },
        },
      },
      scales: {
        x: {
          grid: { color: '#edf2e8' },
          ticks: { font: { size: 11 }, maxTicksLimit: 10 },
        },
        y: {
          grid: { color: '#edf2e8' },
          ticks: {
            font: { size: 11 },
            callback: v => `${v} ${unit}`,
          },
        },
      },
    };
  }

  // Diagramme beim Wechsel zum Chart-Tab bauen
  if (chartTabBtn) {
    chartTabBtn.addEventListener('shown.bs.tab', buildCharts);
  }

  // ─── Hilfsfunktionen ──────────────────────────────────────────────────────
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
})();
