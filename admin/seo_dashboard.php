<?php
/**
 * Gilaf AI SEO Intelligence Engine — Dashboard
 * Enterprise-grade SEO + Content Intelligence Platform
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

require_admin();

$pageTitle = 'AI SEO Intelligence Engine — Gilaf Admin';
$adminPage = 'seo_intelligence';
$db = get_db_connection();

// Get blog list for dropdowns
$blogs = $db->query("SELECT id, title, slug, status FROM blogs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/admin_header.php';
?>

<style>
/* ============================================================
   SEO INTELLIGENCE ENGINE — DASHBOARD STYLES
   ============================================================ */
:root {
    --seo-green: #22c55e;
    --seo-yellow: #eab308;
    --seo-red: #ef4444;
    --seo-blue: #3b82f6;
    --seo-purple: #8b5cf6;
    --seo-dark: #1a3c34;
    --seo-gold: #c5a059;
}

.seo-engine-wrap { max-width: 1400px; margin: 0 auto; }

/* Header */
.seo-header {
    background: linear-gradient(135deg, #1a3c34 0%, #2d6a4f 50%, #1a3c34 100%);
    border-radius: 16px;
    padding: 30px 35px;
    color: white;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.seo-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(197,160,89,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.seo-header h1 {
    font-size: 1.6rem;
    font-weight: 800;
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.seo-header h1 i { color: var(--seo-gold); }
.seo-header p { opacity: 0.8; margin: 0; font-size: 0.9rem; }

/* Tab Navigation */
.seo-tabs {
    display: flex;
    gap: 4px;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 12px;
    margin-bottom: 24px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.seo-tab {
    padding: 10px 18px;
    border: none;
    background: transparent;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    color: #64748b;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}
.seo-tab:hover { background: #e2e8f0; color: #334155; }
.seo-tab.active { background: white; color: var(--seo-dark); box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.seo-tab i { font-size: 0.85rem; }

/* Tab Panels */
.seo-panel { display: none; }
.seo-panel.active { display: block; }

/* Stat Cards */
.seo-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.seo-stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    border: 1px solid #e2e8f0;
    text-align: center;
    transition: all 0.2s;
}
.seo-stat-card:hover { border-color: var(--seo-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
.seo-stat-card .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.1rem;
}
.seo-stat-card .stat-value { font-size: 1.8rem; font-weight: 800; color: #1e293b; }
.seo-stat-card .stat-label { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }

/* Score Circle */
.score-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    font-size: 1.8rem;
    font-weight: 800;
    margin: 0 auto 10px;
    border: 5px solid;
    transition: all 0.5s;
}
.score-circle.green { border-color: var(--seo-green); color: var(--seo-green); background: rgba(34,197,94,0.05); }
.score-circle.yellow { border-color: var(--seo-yellow); color: var(--seo-yellow); background: rgba(234,179,8,0.05); }
.score-circle.red { border-color: var(--seo-red); color: var(--seo-red); background: rgba(239,68,68,0.05); }
.score-circle small { font-size: 0.65rem; font-weight: 600; opacity: 0.7; }

/* Analysis Cards */
.seo-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    margin-bottom: 16px;
    overflow: hidden;
}
.seo-card-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    user-select: none;
}
.seo-card-header h3 {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.seo-card-header .card-score {
    font-size: 0.85rem;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
}
.seo-card-header .card-score.green { background: rgba(34,197,94,0.1); color: #16a34a; }
.seo-card-header .card-score.yellow { background: rgba(234,179,8,0.1); color: #ca8a04; }
.seo-card-header .card-score.red { background: rgba(239,68,68,0.1); color: #dc2626; }
.seo-card-body { padding: 16px 20px; }
.seo-card.collapsed .seo-card-body { display: none; }

/* Check Items */
.seo-check {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 0.85rem;
}
.seo-check:last-child { border-bottom: none; }
.seo-check i { margin-top: 2px; font-size: 0.85rem; flex-shrink: 0; }
.seo-check i.pass { color: var(--seo-green); }
.seo-check i.warn { color: var(--seo-yellow); }
.seo-check i.fail { color: var(--seo-red); }
.seo-check span { color: #475569; line-height: 1.4; }

/* Table */
.seo-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.seo-table th {
    text-align: left;
    padding: 10px 14px;
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e2e8f0;
}
.seo-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
.seo-table tr:hover td { background: #fafbfc; }
.seo-table .score-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 0.78rem;
    font-weight: 700;
}

/* Buttons */
.seo-btn {
    padding: 8px 18px;
    border: none;
    border-radius: 8px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
}
.seo-btn-primary { background: var(--seo-dark); color: white; }
.seo-btn-primary:hover { background: #2d5a4e; }
.seo-btn-gold { background: var(--seo-gold); color: white; }
.seo-btn-gold:hover { background: #b8933e; }
.seo-btn-outline { background: white; color: var(--seo-dark); border: 1px solid #e2e8f0; }
.seo-btn-outline:hover { border-color: var(--seo-dark); }
.seo-btn-sm { padding: 5px 12px; font-size: 0.78rem; }

/* Input */
.seo-input {
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.88rem;
    width: 100%;
    transition: border-color 0.2s;
}
.seo-input:focus { border-color: var(--seo-gold); outline: none; box-shadow: 0 0 0 3px rgba(197,160,89,0.1); }
.seo-select { appearance: auto; }

/* Loading */
.seo-loading {
    text-align: center;
    padding: 40px;
    color: #94a3b8;
}
.seo-loading i { font-size: 2rem; margin-bottom: 10px; color: var(--seo-gold); }

/* Issues */
.issue-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.72rem;
    font-weight: 700;
}
.issue-badge.error { background: rgba(239,68,68,0.1); color: #dc2626; }
.issue-badge.warning { background: rgba(234,179,8,0.1); color: #ca8a04; }

/* Opportunity card */
.opp-card {
    background: #fafbfc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 18px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}
.opp-card .opp-info { flex: 1; }
.opp-card .opp-info h5 { font-size: 0.88rem; font-weight: 600; margin: 0 0 4px; color: #1e293b; }
.opp-card .opp-info p { font-size: 0.78rem; color: #64748b; margin: 0; }

/* AI Result Card */
.ai-result {
    background: linear-gradient(135deg, #f0f9ff, #f8fafc);
    border: 1px solid #e0e7ff;
    border-radius: 12px;
    padding: 20px;
    margin-top: 16px;
}
.ai-result h4 { font-size: 0.95rem; font-weight: 700; color: var(--seo-dark); margin: 0 0 12px; }
.ai-result ul { margin: 8px 0; padding-left: 20px; }
.ai-result li { font-size: 0.85rem; color: #475569; margin-bottom: 6px; line-height: 1.5; }

/* Grid helpers */
.seo-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.seo-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
@media (max-width: 768px) {
    .seo-grid-2, .seo-grid-3 { grid-template-columns: 1fr; }
    .seo-tabs { flex-wrap: nowrap; }
    .seo-header { padding: 20px; }
}

/* ============================================================
   V5 DETAILED SEO ANALYSIS — Enterprise Accordion UI
   ============================================================ */
.da-section-header {
    display: flex; align-items: center; justify-content: space-between;
    background: linear-gradient(135deg, #1a3c34 0%, #2d6a4f 100%);
    border-radius: 12px; padding: 18px 24px; margin: 24px 0 16px; color: white;
}
.da-section-header h2 { margin: 0; font-size: 1.05rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
.da-section-header h2 i { color: var(--seo-gold); }
.da-summary-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.da-pill { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
.da-pill.critical { background: rgba(239,68,68,0.25); color: #fca5a5; border: 1px solid rgba(239,68,68,0.4); }
.da-pill.warning  { background: rgba(234,179,8,0.25);  color: #fde68a; border: 1px solid rgba(234,179,8,0.4); }
.da-pill.moderate { background: rgba(59,130,246,0.25); color: #bfdbfe; border: 1px solid rgba(59,130,246,0.4); }
.da-pill.good     { background: rgba(34,197,94,0.25);  color: #bbf7d0; border: 1px solid rgba(34,197,94,0.4); }

/* Issue accordion card */
.da-issue {
    background: white; border: 1px solid #e2e8f0; border-radius: 12px;
    margin-bottom: 10px; overflow: hidden; transition: box-shadow 0.2s;
}
.da-issue:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
.da-issue.da-open { border-color: #c5a059; box-shadow: 0 4px 20px rgba(197,160,89,0.15); }
.da-issue-header {
    display: flex; align-items: center; gap: 12px; padding: 14px 18px;
    cursor: pointer; user-select: none; transition: background 0.15s;
}
.da-issue-header:hover { background: #fafbfc; }
.da-severity-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}
.da-sev-critical { background: #ef4444; box-shadow: 0 0 6px rgba(239,68,68,0.5); }
.da-sev-warning  { background: #eab308; box-shadow: 0 0 6px rgba(234,179,8,0.5); }
.da-sev-moderate { background: #3b82f6; box-shadow: 0 0 6px rgba(59,130,246,0.4); }
.da-sev-good     { background: #22c55e; box-shadow: 0 0 6px rgba(34,197,94,0.4); }
.da-issue-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; flex: 1; }
.da-badges { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.da-badge {
    padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700;
    display: inline-flex; align-items: center; gap: 3px;
}
.da-badge-module  { background: rgba(139,92,246,0.1); color: #7c3aed; }
.da-badge-impact-high   { background: rgba(239,68,68,0.1); color: #dc2626; }
.da-badge-impact-medium { background: rgba(234,179,8,0.1); color: #ca8a04; }
.da-badge-impact-low    { background: rgba(34,197,94,0.1);  color: #16a34a; }
.da-badge-gain    { background: rgba(197,160,89,0.15); color: #92400e; }
.da-chevron { color: #94a3b8; transition: transform 0.25s; font-size: 0.85rem; }
.da-open .da-chevron { transform: rotate(180deg); }

/* Issue body */
.da-issue-body { display: none; border-top: 1px solid #f1f5f9; }
.da-open .da-issue-body { display: block; }
.da-body-inner { padding: 20px; }

/* Meta bar */
.da-meta-bar {
    display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 18px;
    padding: 12px 16px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;
}
.da-meta-item { display: flex; flex-direction: column; align-items: center; min-width: 80px; }
.da-meta-label { font-size: 0.65rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.da-meta-value { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-top: 2px; }

/* Impact meter */
.da-impact-meter { display: flex; align-items: center; gap: 8px; }
.da-meter-bar { flex: 1; height: 6px; border-radius: 3px; background: #e2e8f0; overflow: hidden; }
.da-meter-fill { height: 100%; border-radius: 3px; }
.da-meter-high   .da-meter-fill { background: linear-gradient(90deg, #ef4444, #dc2626); width: 90%; }
.da-meter-medium .da-meter-fill { background: linear-gradient(90deg, #eab308, #ca8a04); width: 55%; }
.da-meter-low    .da-meter-fill { background: linear-gradient(90deg, #22c55e, #16a34a); width: 25%; }

/* Sections inside body */
.da-section { margin-bottom: 16px; }
.da-section-label {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;
    color: #94a3b8; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
}
.da-section-label i { font-size: 0.78rem; }

/* Explanation box */
.da-explanation {
    background: #f8fafc; border-radius: 10px; padding: 14px; border-left: 3px solid var(--seo-purple);
}
.da-expl-row { display: flex; gap: 8px; margin-bottom: 8px; font-size: 0.83rem; }
.da-expl-row:last-child { margin-bottom: 0; }
.da-expl-key { font-weight: 700; color: #7c3aed; min-width: 40px; flex-shrink: 0; }
.da-expl-val { color: #475569; line-height: 1.5; }

/* Location cards */
.da-location {
    background: #fef9f0; border: 1px solid #fed7aa; border-radius: 8px; padding: 10px 14px;
    margin-bottom: 6px; font-size: 0.82rem;
}
.da-location .da-loc-section { font-weight: 700; color: #92400e; margin-bottom: 4px; }
.da-location .da-loc-text { color: #78350f; font-style: italic; line-height: 1.4; }
.da-location .da-loc-meta { font-size: 0.72rem; color: #b45309; margin-top: 4px; }

/* Fix suggestions */
.da-fix-list { padding: 0; margin: 0; list-style: none; }
.da-fix-item {
    display: flex; align-items: flex-start; gap: 10px; padding: 8px 12px;
    background: #f0fdf4; border-radius: 8px; margin-bottom: 6px; font-size: 0.83rem; color: #166534;
}
.da-fix-num {
    min-width: 22px; height: 22px; background: #16a34a; color: white; border-radius: 50%;
    font-size: 0.7rem; font-weight: 700; display: flex; align-items: center; justify-content: center;
}

/* Before / After */
.da-before-after { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
@media (max-width: 600px) { .da-before-after { grid-template-columns: 1fr; } }
.da-before, .da-after {
    border-radius: 10px; padding: 12px 14px; font-size: 0.82rem; line-height: 1.5;
}
.da-before { background: #fef2f2; border: 1px solid #fecaca; color: #7f1d1d; }
.da-after  { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
.da-ba-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.da-before .da-ba-label { color: #dc2626; }
.da-after  .da-ba-label { color: #16a34a; }

/* AI suggestions */
.da-ai-list { padding: 0; margin: 0; list-style: none; }
.da-ai-item {
    display: flex; align-items: flex-start; gap: 8px; padding: 6px 0;
    border-bottom: 1px solid #f1f5f9; font-size: 0.82rem; color: #475569;
}
.da-ai-item:last-child { border-bottom: none; }
.da-ai-item i { color: var(--seo-purple); margin-top: 2px; flex-shrink: 0; font-size: 0.78rem; }

/* Priority order */
.da-priority-list { display: flex; flex-direction: column; gap: 6px; }
.da-priority-item {
    display: flex; align-items: center; gap: 10px; padding: 8px 14px;
    border-radius: 8px; font-size: 0.82rem;
}
.da-priority-item.p1 { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
.da-priority-item.p2 { background: #fefce8; color: #713f12; border: 1px solid #fde68a; }
.da-priority-item.p3 { background: #f0f9ff; color: #0c4a6e; border: 1px solid #bae6fd; }
.da-priority-num { font-weight: 800; font-size: 1rem; min-width: 20px; }

/* Readability breakdown panel */
.da-readability-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 16px;
}
.da-read-metric {
    background: #fafbfc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px;
    text-align: center;
}
.da-read-val { font-size: 1.3rem; font-weight: 800; margin-bottom: 4px; }
.da-read-label { font-size: 0.7rem; color: #94a3b8; font-weight: 600; text-transform: uppercase; }
.da-read-sub { font-size: 0.72rem; color: #64748b; margin-top: 2px; }

/* Go to editor button */
.da-goto-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 18px;
    background: var(--seo-dark); color: white; border-radius: 8px; text-decoration: none;
    font-size: 0.82rem; font-weight: 600; margin-top: 14px; border: none; cursor: pointer;
    transition: background 0.2s;
}
.da-goto-btn:hover { background: #2d5a4e; color: white; }
.da-goto-btn.gold { background: var(--seo-gold); }
.da-goto-btn.gold:hover { background: #b8933e; }

/* ============================================================
   THIN PARAGRAPHS AI PANEL — Premium Enterprise v5
   ============================================================ */
.tp-header-banner {
    background: linear-gradient(135deg, #1e0038 0%, #4c0070 50%, #7c1eb4 100%);
    border-radius: 14px; padding: 22px 26px; margin-bottom: 20px;
    color: white; position: relative; overflow: hidden;
}
.tp-header-banner::before {
    content:''; position:absolute; top:-40px; right:-40px; width:180px; height:180px;
    background: rgba(255,255,255,0.05); border-radius: 50%;
}
.tp-header-banner::after {
    content:''; position:absolute; bottom:-60px; left:30%; width:250px; height:250px;
    background: rgba(255,255,255,0.04); border-radius: 50%;
}
.tp-banner-title {
    font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: 10px;
    margin-bottom: 16px; position: relative; z-index: 1;
}
.tp-banner-title i { color: #f59e0b; font-size: 1.1rem; }
.tp-banner-title small { font-size:0.65em; opacity:0.65; font-weight:400; }
.tp-stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px; position: relative; z-index: 1;
}
.tp-stat {
    background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px; padding: 12px 16px; backdrop-filter: blur(4px);
}
.tp-stat-val { font-size: 1.6rem; font-weight: 900; line-height: 1; margin-bottom: 4px; }
.tp-stat-val.danger  { color: #fca5a5; }
.tp-stat-val.warn    { color: #fde68a; }
.tp-stat-val.info    { color: #93c5fd; }
.tp-stat-val.success { color: #86efac; }
.tp-stat-label { font-size: 0.68rem; opacity: 0.75; text-transform: uppercase; letter-spacing: 0.5px; }

/* Sticky nav bar */
.tp-sticky-nav {
    position: sticky; top: 0; z-index: 10;
    background: #fff; border-bottom: 2px solid #e2e8f0;
    padding: 10px 16px; margin: 0 0 16px;
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);
}
.tp-nav-label { font-size: 0.75rem; font-weight: 700; color: #64748b; }
.tp-nav-filter {
    padding: 4px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
    border: 1.5px solid #e2e8f0; background: white; cursor: pointer;
    transition: all 0.15s; color: #64748b;
}
.tp-nav-filter:hover, .tp-nav-filter.active { background: var(--seo-dark); color: white; border-color: var(--seo-dark); }
.tp-bulk-btn {
    margin-left: auto; padding: 7px 16px; border-radius: 8px;
    background: linear-gradient(135deg, #7c1eb4, #4c0070);
    color: white; font-size: 0.78rem; font-weight: 700; border: none; cursor: pointer;
    display: flex; align-items: center; gap: 6px; transition: opacity 0.2s;
}
.tp-bulk-btn:hover { opacity: 0.88; }

/* Score prediction bar */
.tp-score-predict {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1.5px solid #86efac; border-radius: 12px;
    padding: 16px 20px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}
.tp-score-col { text-align: center; min-width: 80px; }
.tp-score-num { font-size: 1.6rem; font-weight: 900; line-height: 1; }
.tp-score-num.current { color: #dc2626; }
.tp-score-num.predicted { color: #16a34a; }
.tp-score-lbl { font-size: 0.68rem; color: #64748b; text-transform: uppercase; font-weight: 600; margin-top: 3px; }
.tp-score-arrow { font-size: 1.8rem; color: #16a34a; font-weight: 900; }
.tp-score-gain-badge {
    background: #16a34a; color: white; border-radius: 20px;
    padding: 4px 14px; font-size: 0.8rem; font-weight: 800;
}
.tp-score-desc { font-size: 0.78rem; color: #166534; flex: 1; min-width: 160px; }

/* Paragraph cards */
.tp-para-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.tp-para-card {
    background: white; border: 1.5px solid #e2e8f0;
    border-radius: 12px; overflow: hidden; transition: box-shadow 0.2s;
}
.tp-para-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.07); }
.tp-para-card.tp-open { border-color: #7c1eb4; box-shadow: 0 4px 20px rgba(124,30,180,0.12); }
.tp-para-card.sev-very_thin { border-left: 4px solid #ef4444; }
.tp-para-card.sev-thin      { border-left: 4px solid #eab308; }
.tp-para-card.sev-moderate  { border-left: 4px solid #3b82f6; }

.tp-para-header {
    display: flex; align-items: center; gap: 10px; padding: 12px 16px;
    cursor: pointer; user-select: none;
}
.tp-para-header:hover { background: #fafbfc; }
.tp-para-num {
    min-width: 36px; height: 36px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.78rem; font-weight: 800; flex-shrink: 0;
    background: #f1f5f9; color: #475569;
}
.sev-very_thin .tp-para-num { background: #fef2f2; color: #dc2626; }
.sev-thin      .tp-para-num { background: #fefce8; color: #ca8a04; }
.sev-moderate  .tp-para-num { background: #eff6ff; color: #2563eb; }

.tp-para-info { flex: 1; min-width: 0; }
.tp-para-section { font-size: 0.72rem; color: #94a3b8; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tp-para-preview { font-size: 0.82rem; color: #1e293b; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 380px; }

.tp-para-meta { display: flex; align-items: center; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.tp-wc-badge {
    padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 800;
}
.sev-very_thin .tp-wc-badge { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.sev-thin      .tp-wc-badge { background: #fefce8; color: #ca8a04; border: 1px solid #fde68a; }
.sev-moderate  .tp-wc-badge { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.tp-sev-badge {
    padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.3px;
}
.sev-very_thin .tp-sev-badge { background: rgba(239,68,68,0.1); color: #ef4444; }
.sev-thin      .tp-sev-badge { background: rgba(234,179,8,0.1);  color: #ca8a04; }
.sev-moderate  .tp-sev-badge { background: rgba(59,130,246,0.1); color: #2563eb; }
.tp-expand-icon { color: #94a3b8; transition: transform 0.25s; font-size: 0.85rem; }
.tp-open .tp-expand-icon { transform: rotate(180deg); }

/* Paragraph card body */
.tp-para-body { display: none; border-top: 1px solid #f1f5f9; padding: 18px; }
.tp-open .tp-para-body { display: block; }
.tp-para-body-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
@media (max-width: 700px) { .tp-para-body-grid { grid-template-columns: 1fr; } }

/* Word count visual bar */
.tp-wc-visual { margin-bottom: 16px; }
.tp-wc-visual-label { display: flex; justify-content: space-between; font-size: 0.72rem; color: #64748b; margin-bottom: 5px; }
.tp-wc-track { height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; position: relative; }
.tp-wc-fill { height: 100%; border-radius: 6px; transition: width 0.7s; }
.tp-wc-fill.very_thin { background: linear-gradient(90deg, #ef4444, #dc2626); }
.tp-wc-fill.thin      { background: linear-gradient(90deg, #eab308, #ca8a04); }
.tp-wc-fill.moderate  { background: linear-gradient(90deg, #3b82f6, #2563eb); }
.tp-wc-fill.good      { background: linear-gradient(90deg, #22c55e, #16a34a); }
.tp-wc-target { position: absolute; top: 0; bottom: 0; left: calc(50/100*100%); width: 2px; background: rgba(0,0,0,0.2); }

/* Depth score meter */
.tp-depth-meter { margin-bottom: 4px; }
.tp-depth-track { height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
.tp-depth-fill { height: 100%; border-radius: 4px; transition: width 0.7s; }

/* Content preview box */
.tp-content-preview {
    background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 10px;
    padding: 12px 14px; margin-bottom: 14px;
}
.tp-preview-label { font-size: 0.68rem; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.tp-preview-text { font-size: 0.83rem; color: #78350f; line-height: 1.6; font-style: italic; }
.tp-highlight-weak { background: rgba(239,68,68,0.15); border-bottom: 2px solid #ef4444; border-radius: 2px; padding: 0 2px; }

/* Missing elements grid */
.tp-missing-grid { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.tp-missing-tag {
    padding: 3px 10px; border-radius: 20px; font-size: 0.68rem; font-weight: 700;
    display: flex; align-items: center; gap: 4px;
}
.tp-missing-tag.explanation  { background: rgba(124,30,180,0.1); color: #7c1eb4; border: 1px solid rgba(124,30,180,0.2); }
.tp-missing-tag.example       { background: rgba(245,158,11,0.1); color: #b45309; border: 1px solid rgba(245,158,11,0.2); }
.tp-missing-tag.statistics    { background: rgba(59,130,246,0.1); color: #1d4ed8; border: 1px solid rgba(59,130,246,0.2); }
.tp-missing-tag.benefits      { background: rgba(34,197,94,0.1);  color: #15803d; border: 1px solid rgba(34,197,94,0.2); }
.tp-missing-tag.user_intent   { background: rgba(236,72,153,0.1); color: #be185d; border: 1px solid rgba(236,72,153,0.2); }
.tp-missing-tag.comparison    { background: rgba(249,115,22,0.1); color: #c2410c; border: 1px solid rgba(249,115,22,0.2); }
.tp-missing-tag.authority     { background: rgba(239,68,68,0.1);  color: #b91c1c; border: 1px solid rgba(239,68,68,0.2); }
.tp-missing-tag.depth         { background: rgba(15,23,42,0.08);  color: #1e293b; border: 1px solid rgba(15,23,42,0.15); }

/* AI Rewrite box */
.tp-rewrite-box {
    background: linear-gradient(135deg, #faf5ff, #ede9fe);
    border: 1.5px solid #c4b5fd; border-radius: 10px; padding: 14px;
    margin-bottom: 14px;
}
.tp-rewrite-label { font-size: 0.68rem; font-weight: 700; color: #6d28d9; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.tp-rewrite-text { font-size: 0.83rem; color: #4c1d95; line-height: 1.65; }

/* Fix action buttons */
.tp-fix-btns { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.tp-fix-btn {
    padding: 6px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;
    border: 1.5px solid; cursor: pointer; display: flex; align-items: center; gap: 5px;
    transition: all 0.15s; background: white;
}
.tp-fix-btn:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.tp-fix-btn.ai     { color: #7c1eb4; border-color: #c4b5fd; }
.tp-fix-btn.merge  { color: #1d4ed8; border-color: #93c5fd; }
.tp-fix-btn.detail { color: #b45309; border-color: #fde68a; }
.tp-fix-btn.sem    { color: #15803d; border-color: #86efac; }
.tp-fix-btn.faq    { color: #be185d; border-color: #fbcfe8; }
.tp-fix-btn.human  { color: #0369a1; border-color: #bae6fd; }

/* SEO impact section */
.tp-impact-section {
    background: linear-gradient(135deg, #fff7ed, #ffedd5);
    border: 1.5px solid #fed7aa; border-radius: 12px; padding: 18px 20px; margin-bottom: 18px;
}
.tp-impact-title { font-size: 0.85rem; font-weight: 800; color: #7c2d12; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
.tp-impact-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
.tp-impact-item { display: flex; align-items: flex-start; gap: 8px; font-size: 0.82rem; color: #9a3412; }
.tp-impact-item i { color: #ef4444; margin-top: 2px; flex-shrink: 0; }
.tp-google-note {
    margin-top: 12px; padding: 10px 14px;
    background: rgba(0,0,0,0.05); border-radius: 8px;
    font-size: 0.78rem; color: #7c2d12;
}
.tp-google-note strong { display: block; margin-bottom: 4px; }

/* Section sub-label */
.tp-section-lbl {
    font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: #94a3b8; margin-bottom: 8px;
    display: flex; align-items: center; gap: 6px;
}
.tp-section-lbl i { font-size: 0.75rem; }

/* View in content button */
.tp-view-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 8px;
    background: var(--seo-dark); color: white;
    font-size: 0.75rem; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer;
    transition: background 0.2s;
}
.tp-view-btn:hover { background: #2d5a4e; color: white; }
.tp-view-btn.purple { background: #7c1eb4; }
.tp-view-btn.purple:hover { background: #5b169e; }

/* ═══════════════════════════════════════════════════════════════
   SMART SEO AUTO FIX ENGINE STYLES
   ═══════════════════════════════════════════════════════════════ */
.seo-fix-toolbar { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:12px 0; padding:12px 16px; background:linear-gradient(135deg,#f8fafc,#f1f5f9); border:1px solid #e2e8f0; border-radius:10px; }
.seo-fix-toolbar .fix-mode-select { padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1; font-size:0.8rem; font-weight:600; background:white; cursor:pointer; }
.seo-fix-toolbar .fix-btn { padding:7px 14px; border-radius:6px; border:none; font-size:0.78rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:all 0.2s; }
.seo-fix-toolbar .fix-btn-primary { background:linear-gradient(135deg,#3b82f6,#2563eb); color:white; }
.seo-fix-toolbar .fix-btn-primary:hover { background:linear-gradient(135deg,#2563eb,#1d4ed8); transform:translateY(-1px); box-shadow:0 3px 8px rgba(37,99,235,0.3); }
.seo-fix-toolbar .fix-btn-success { background:linear-gradient(135deg,#22c55e,#16a34a); color:white; }
.seo-fix-toolbar .fix-btn-success:hover { background:linear-gradient(135deg,#16a34a,#15803d); transform:translateY(-1px); }
.seo-fix-toolbar .fix-btn-warning { background:linear-gradient(135deg,#f59e0b,#d97706); color:white; }
.seo-fix-toolbar .fix-btn-outline { background:white; border:1px solid #cbd5e1; color:#475569; }
.seo-fix-toolbar .fix-btn-outline:hover { border-color:#3b82f6; color:#3b82f6; }
.seo-fix-toolbar .fix-btn-danger { background:linear-gradient(135deg,#ef4444,#dc2626); color:white; }

.fix-preview-panel { margin:12px 0; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; animation:fixSlideIn 0.3s ease; }
@keyframes fixSlideIn { from{opacity:0;transform:translateY(-8px);} to{opacity:1;transform:translateY(0);} }
.fix-preview-header { padding:12px 16px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom:1px solid #bfdbfe; display:flex; align-items:center; justify-content:space-between; }
.fix-preview-header h4 { margin:0; font-size:0.88rem; color:#1e40af; display:flex; align-items:center; gap:6px; }
.fix-preview-header .confidence-badge { padding:3px 10px; border-radius:12px; font-size:0.72rem; font-weight:700; }
.confidence-high { background:#dcfce7; color:#166534; }
.confidence-med { background:#fef3c7; color:#92400e; }
.confidence-low { background:#fecaca; color:#991b1b; }

.fix-diff-container { display:grid; grid-template-columns:1fr 1fr; gap:0; }
.fix-diff-col { padding:14px 16px; }
.fix-diff-col.original { background:#fef2f2; border-right:1px solid #e2e8f0; }
.fix-diff-col.fixed { background:#f0fdf4; }
.fix-diff-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:8px; display:flex; align-items:center; gap:5px; }
.fix-diff-col.original .fix-diff-label { color:#dc2626; }
.fix-diff-col.fixed .fix-diff-label { color:#16a34a; }
.fix-diff-text { font-size:0.82rem; line-height:1.6; color:#334155; white-space:pre-wrap; word-break:break-word; max-height:200px; overflow-y:auto; }
.fix-diff-text del { background:#fecaca; text-decoration:line-through; color:#991b1b; padding:1px 2px; border-radius:2px; }
.fix-diff-text ins { background:#bbf7d0; text-decoration:none; color:#166534; padding:1px 2px; border-radius:2px; }

.fix-metrics-bar { display:flex; flex-wrap:wrap; gap:8px; padding:10px 16px; background:#f8fafc; border-top:1px solid #e2e8f0; }
.fix-metric { display:flex; align-items:center; gap:4px; font-size:0.73rem; font-weight:600; padding:4px 10px; border-radius:6px; background:white; border:1px solid #e2e8f0; }
.fix-metric.positive { border-color:#86efac; color:#166534; }
.fix-metric.negative { border-color:#fecaca; color:#991b1b; }
.fix-metric.neutral { border-color:#fde68a; color:#92400e; }
.fix-metric i { font-size:0.7rem; }

.fix-actions-bar { display:flex; align-items:center; gap:8px; padding:10px 16px; border-top:1px solid #e2e8f0; background:white; }
.fix-actions-bar .fix-btn { font-size:0.76rem; padding:6px 12px; }

.fix-reasoning { padding:10px 16px; font-size:0.78rem; color:#475569; background:#fffbeb; border-top:1px solid #fde68a; line-height:1.5; }
.fix-reasoning i { color:#f59e0b; margin-right:4px; }

.fix-changes-list { padding:8px 16px 12px; border-top:1px solid #e2e8f0; }
.fix-changes-list h5 { margin:0 0 6px; font-size:0.76rem; color:#475569; }
.fix-changes-list ul { margin:0; padding-left:16px; list-style:none; }
.fix-changes-list li { font-size:0.75rem; color:#64748b; padding:2px 0; position:relative; }
.fix-changes-list li::before { content:'\2713'; position:absolute; left:-14px; color:#22c55e; font-weight:700; }

.fix-progress-overlay { position:absolute; inset:0; background:rgba(255,255,255,0.92); display:flex; flex-direction:column; align-items:center; justify-content:center; z-index:10; border-radius:10px; }
.fix-progress-overlay .spinner { width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#3b82f6; border-radius:50%; animation:spin 0.8s linear infinite; margin-bottom:10px; }
@keyframes spin { to{transform:rotate(360deg);} }
.fix-progress-overlay p { font-size:0.82rem; color:#475569; font-weight:600; margin:0; }

.fix-log-panel { margin-top:12px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.fix-log-header { padding:10px 14px; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:0.8rem; font-weight:700; color:#334155; display:flex; align-items:center; gap:6px; }
.fix-log-entry { padding:8px 14px; border-bottom:1px solid #f1f5f9; font-size:0.76rem; display:flex; align-items:center; gap:8px; }
.fix-log-entry:last-child { border-bottom:none; }
.fix-log-entry .log-status { width:6px; height:6px; border-radius:50%; background:#22c55e; flex-shrink:0; }
.fix-log-entry .log-issue { font-weight:600; color:#334155; flex:1; }
.fix-log-entry .log-time { color:#94a3b8; font-size:0.7rem; }
.fix-log-entry .log-undo { color:#3b82f6; cursor:pointer; font-weight:600; font-size:0.72rem; }
.fix-log-entry .log-undo:hover { text-decoration:underline; }

.bulk-fix-results { margin-top:12px; }
.bulk-fix-item { display:flex; align-items:center; gap:8px; padding:6px 12px; border-radius:6px; margin-bottom:4px; font-size:0.78rem; }
.bulk-fix-item.fixed { background:#f0fdf4; border:1px solid #bbf7d0; }
.bulk-fix-item.failed { background:#fef2f2; border:1px solid #fecaca; }
.bulk-fix-item.skipped { background:#f8fafc; border:1px solid #e2e8f0; }
.bulk-fix-item i { font-size:0.7rem; }
.bulk-fix-item.fixed i { color:#22c55e; }
.bulk-fix-item.failed i { color:#ef4444; }
.bulk-fix-item.skipped i { color:#94a3b8; }
</style>

<div class="seo-engine-wrap">
    <!-- Header -->
    <div class="seo-header">
        <h1><i class="fas fa-brain"></i> Gilaf AI SEO Intelligence Engine <small style="font-size:0.5em;opacity:0.7;font-weight:400;">v4.0</small></h1>
        <p>AI-Powered SEO Strategist — Ranking Diagnosis + Product SEO + Core Web Vitals + AI Fixes + Schema Generator + SERP Comparison + CTR Optimization</p>
    </div>

    <!-- Tab Navigation -->
    <div class="seo-tabs" id="seoTabs">
        <button class="seo-tab active" data-tab="overview"><i class="fas fa-chart-pie"></i> Overview</button>
        <button class="seo-tab" data-tab="analyzer"><i class="fas fa-search-plus"></i> Content Analyzer</button>
        <button class="seo-tab" data-tab="bulk"><i class="fas fa-list-check"></i> Bulk Analysis</button>
        <button class="seo-tab" data-tab="technical"><i class="fas fa-cogs"></i> Technical SEO</button>
        <button class="seo-tab" data-tab="linking"><i class="fas fa-link"></i> Internal Linking</button>
        <button class="seo-tab" data-tab="keywords"><i class="fas fa-key"></i> Keyword Research</button>
        <button class="seo-tab" data-tab="clusters"><i class="fas fa-project-diagram"></i> Topic Clusters</button>
        <button class="seo-tab" data-tab="workflow"><i class="fas fa-tasks"></i> Content Workflow</button>
        <button class="seo-tab" data-tab="semantic"><i class="fas fa-atom"></i> Semantic Intel</button>
        <button class="seo-tab" data-tab="authority"><i class="fas fa-crown"></i> Topical Authority</button>
        <button class="seo-tab" data-tab="aisearch"><i class="fas fa-robot"></i> AI Search</button>
        <button class="seo-tab" data-tab="ranking"><i class="fas fa-trophy"></i> Ranking Predict</button>
        <button class="seo-tab" data-tab="serp"><i class="fas fa-globe"></i> SERP Intel</button>
        <button class="seo-tab" data-tab="whynotranking"><i class="fas fa-exclamation-triangle"></i> Why Not Ranking</button>
        <button class="seo-tab" data-tab="productanalyzer"><i class="fas fa-box-open"></i> Product SEO</button>
        <button class="seo-tab" data-tab="pagespeed"><i class="fas fa-tachometer-alt"></i> Core Web Vitals</button>
        <button class="seo-tab" data-tab="aitools"><i class="fas fa-magic"></i> AI Fix & Schema</button>
        <button class="seo-tab" data-tab="apicenter"><i class="fas fa-server"></i> API Center</button>
    </div>

    <!-- ============================================================
         TAB 1: OVERVIEW
         ============================================================ -->
    <div class="seo-panel active" id="panel-overview">
        <div class="seo-stats-grid" id="overviewStats">
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(34,197,94,0.1);color:var(--seo-green);"><i class="fas fa-newspaper"></i></div>
                <div class="stat-value" id="statBlogs">—</div>
                <div class="stat-label">Published Blogs</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(59,130,246,0.1);color:var(--seo-blue);"><i class="fas fa-box"></i></div>
                <div class="stat-value" id="statProducts">—</div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(139,92,246,0.1);color:var(--seo-purple);"><i class="fas fa-heading"></i></div>
                <div class="stat-value" id="statMetaTitles">—</div>
                <div class="stat-label">Blogs with Meta Title</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(197,160,89,0.1);color:var(--seo-gold);"><i class="fas fa-align-left"></i></div>
                <div class="stat-value" id="statMetaDescs">—</div>
                <div class="stat-label">Blogs with Meta Desc</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--seo-red);"><i class="fas fa-key"></i></div>
                <div class="stat-value" id="statKeywords">—</div>
                <div class="stat-label">Blogs with Keywords</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(34,197,94,0.1);color:var(--seo-green);"><i class="fas fa-image"></i></div>
                <div class="stat-value" id="statImages">—</div>
                <div class="stat-label">Blogs with Images</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(59,130,246,0.1);color:var(--seo-blue);"><i class="fas fa-question-circle"></i></div>
                <div class="stat-value" id="statFaqs">—</div>
                <div class="stat-label">Total FAQs</div>
            </div>
            <div class="seo-stat-card">
                <div class="stat-icon" style="background:rgba(139,92,246,0.1);color:var(--seo-purple);"><i class="fas fa-folder"></i></div>
                <div class="stat-value" id="statCategories">—</div>
                <div class="stat-label">Active Categories</div>
            </div>
        </div>

        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--seo-yellow)"></i> Quick Technical Scan</h3></div>
                <div class="seo-card-body" id="quickScanResults">
                    <div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Scanning blogs...</p></div>
                </div>
            </div>
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-link" style="color:var(--seo-blue)"></i> Link Opportunities</h3></div>
                <div class="seo-card-body" id="quickLinkResults">
                    <div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Finding opportunities...</p></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 2: CONTENT ANALYZER
         ============================================================ -->
    <div class="seo-panel" id="panel-analyzer">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-search-plus" style="color:var(--seo-purple)"></i> Deep Content SEO Analyzer</h3></div>
            <div class="seo-card-body">
                <div class="seo-grid-2" style="margin-bottom:16px;">
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Select Blog to Analyze</label>
                        <select id="analyzerBlogSelect" class="seo-input seo-select">
                            <option value="">— Select a blog —</option>
                            <?php foreach ($blogs as $b): ?>
                            <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['title']); ?> (<?= $b['status']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:8px;">
                        <button class="seo-btn seo-btn-primary" onclick="runBlogAnalysis()"><i class="fas fa-search"></i> Analyze</button>
                        <button class="seo-btn seo-btn-gold" onclick="runAiSemantic()"><i class="fas fa-brain"></i> AI Semantic</button>
                        <button class="seo-btn seo-btn-outline" onclick="runAiEeat()"><i class="fas fa-shield-alt"></i> AI E-E-A-T</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="analyzerResults" style="display:none;">
            <div class="seo-grid-2" style="margin-bottom:16px;">
                <div style="text-align:center;">
                    <div class="score-circle" id="overallScoreCircle"><span id="overallScoreVal">—</span><small>OVERALL</small></div>
                    <div style="font-size:0.85rem;color:#64748b;" id="overallScoreLabel"></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;" id="moduleScoresGrid"></div>
            </div>
            <div id="analyzerChecks"></div>
            <div id="detailedAnalysisSection" style="display:none;"></div>
        </div>

        <div id="aiSemanticResults" style="display:none;"></div>
        <div id="aiEeatResults" style="display:none;"></div>
    </div>

    <!-- ============================================================
         TAB 3: BULK ANALYSIS
         ============================================================ -->
    <div class="seo-panel" id="panel-bulk">
        <div class="seo-card">
            <div class="seo-card-header">
                <h3><i class="fas fa-list-check" style="color:var(--seo-green)"></i> Bulk SEO Analysis — All Blogs</h3>
                <button class="seo-btn seo-btn-primary seo-btn-sm" onclick="runBulkAnalysis()"><i class="fas fa-play"></i> Run Analysis</button>
            </div>
            <div class="seo-card-body" id="bulkResults">
                <p style="color:#94a3b8;text-align:center;padding:20px;">Click "Run Analysis" to scan all blogs</p>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 4: TECHNICAL SEO
         ============================================================ -->
    <div class="seo-panel" id="panel-technical">
        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header">
                    <h3><i class="fas fa-blog" style="color:var(--seo-blue)"></i> Blog SEO Issues</h3>
                    <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="scanBlogs()"><i class="fas fa-sync"></i> Scan</button>
                </div>
                <div class="seo-card-body" id="blogScanResults">
                    <p style="color:#94a3b8;text-align:center;">Click Scan to check all blogs</p>
                </div>
            </div>
            <div class="seo-card">
                <div class="seo-card-header">
                    <h3><i class="fas fa-box" style="color:var(--seo-purple)"></i> Product SEO Issues</h3>
                    <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="scanProducts()"><i class="fas fa-sync"></i> Scan</button>
                </div>
                <div class="seo-card-body" id="productScanResults">
                    <p style="color:#94a3b8;text-align:center;">Click Scan to check all products</p>
                </div>
            </div>
        </div>

        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-ghost" style="color:var(--seo-red)"></i> Autonomous Orphan Intelligence Engine</h3>
                <div style="display:flex;gap:6px;">
                    <button class="seo-btn seo-btn-primary seo-btn-sm" onclick="findOrphans()"><i class="fas fa-search"></i> Detect Orphans</button>
                    <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="loadConnectivityGraph()"><i class="fas fa-project-diagram"></i> Link Graph</button>
                </div>
            </div>
            <div class="seo-card-body" id="orphanResults">
                <p style="color:#94a3b8;text-align:center;">Detect orphan pages with connectivity scoring, risk assessment &amp; one-click auto-fix</p>
            </div>
        </div>
        <!-- Auto-Fix Modal (hidden by default) -->
        <div class="seo-card" id="autoFixPanel" style="display:none;">
            <div class="seo-card-header"><h3><i class="fas fa-magic" style="color:var(--seo-gold)"></i> Auto-Fix: <span id="autoFixTitle"></span></h3>
                <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="document.getElementById('autoFixPanel').style.display='none'"><i class="fas fa-times"></i> Close</button>
            </div>
            <div class="seo-card-body" id="autoFixResults"></div>
        </div>
        <!-- Connectivity Graph -->
        <div class="seo-card" id="graphPanel" style="display:none;">
            <div class="seo-card-header"><h3><i class="fas fa-project-diagram" style="color:var(--seo-blue)"></i> Content Connectivity Graph</h3>
                <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="document.getElementById('graphPanel').style.display='none'"><i class="fas fa-times"></i> Close</button>
            </div>
            <div class="seo-card-body" id="graphResults"></div>
        </div>
    </div>

    <!-- ============================================================
         TAB 5: INTERNAL LINKING (Enterprise Semantic Engine v2)
         ============================================================ -->
    <div class="seo-panel" id="panel-linking">
        <!-- Stats Bar -->
        <div id="linkStatsBar" style="display:none;margin-bottom:16px;">
            <div class="seo-stats-grid" id="linkStatsGrid"></div>
        </div>

        <div class="seo-card">
            <div class="seo-card-header">
                <h3><i class="fas fa-link" style="color:var(--seo-gold)"></i> Semantic Link Opportunities</h3>
                <div style="display:flex;gap:6px;">
                    <button class="seo-btn seo-btn-primary seo-btn-sm" onclick="findLinkOpportunities()"><i class="fas fa-search"></i> Scan All</button>
                    <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="findLinkOpportunities(true)"><i class="fas fa-sync"></i> Force Refresh</button>
                </div>
            </div>
            <div class="seo-card-body" id="linkOpResults">
                <p style="color:#94a3b8;text-align:center;padding:20px;">Enterprise semantic linking — discovers unlinked mentions, keyword overlaps, phrase matches with AI scoring &amp; duplicate prevention</p>
            </div>
        </div>

        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--seo-red)"></i> Keyword Cannibalization</h3></div>
                <div class="seo-card-body" id="cannibResults">
                    <p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Run scan to detect keyword cannibalization</p>
                </div>
            </div>
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-ghost" style="color:var(--seo-purple)"></i> Orphan &amp; Weak Pages</h3></div>
                <div class="seo-card-body" id="orphanWeakResults">
                    <p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Run scan to detect orphan &amp; weak pages</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 6: KEYWORD RESEARCH
         ============================================================ -->
    <div class="seo-panel" id="panel-keywords">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-key" style="color:var(--seo-green)"></i> AI Keyword Research Engine</h3></div>
            <div class="seo-card-body">
                <div class="seo-grid-2" style="margin-bottom:16px;">
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Seed Keyword</label>
                        <input type="text" id="kwResearchInput" class="seo-input" placeholder="e.g. kashmir saffron, olive oil, dry fruits">
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:8px;">
                        <button class="seo-btn seo-btn-gold" onclick="runKeywordResearch()"><i class="fas fa-brain"></i> AI Research</button>
                        <button class="seo-btn seo-btn-outline" onclick="runContentBrief()"><i class="fas fa-file-alt"></i> Generate Brief</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="kwResults" style="display:none;"></div>
        <div id="briefResults" style="display:none;"></div>
    </div>

    <!-- ============================================================
         TAB 7: TOPIC CLUSTERS
         ============================================================ -->
    <div class="seo-panel" id="panel-clusters">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-project-diagram" style="color:var(--seo-purple)"></i> AI Topic Cluster Generator</h3></div>
            <div class="seo-card-body">
                <div class="seo-grid-2" style="margin-bottom:16px;">
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Pillar Topic</label>
                        <input type="text" id="clusterTopicInput" class="seo-input" placeholder="e.g. Kashmir Saffron Guide, Olive Oil Benefits">
                    </div>
                    <div style="display:flex;align-items:flex-end;">
                        <button class="seo-btn seo-btn-primary" onclick="generateCluster()"><i class="fas fa-brain"></i> Generate Cluster</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="clusterResults" style="display:none;"></div>
    </div>

    <!-- ============================================================
         TAB 8: CONTENT WORKFLOW
         ============================================================ -->
    <div class="seo-panel" id="panel-workflow">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-tasks" style="color:var(--seo-blue)"></i> Publishing Checklist</h3></div>
            <div class="seo-card-body">
                <div style="margin-bottom:16px;">
                    <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Select Blog</label>
                    <div style="display:flex;gap:8px;">
                        <select id="workflowBlogSelect" class="seo-input seo-select" style="flex:1;">
                            <option value="">— Select a blog —</option>
                            <?php foreach ($blogs as $b): ?>
                            <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['title']); ?> (<?= $b['status']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button class="seo-btn seo-btn-primary" onclick="loadChecklist()"><i class="fas fa-check-double"></i> Check</button>
                    </div>
                </div>
                <div id="workflowResults"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 9: SEMANTIC INTELLIGENCE
         ============================================================ -->
    <div class="seo-panel" id="panel-semantic">
        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-atom" style="color:var(--seo-purple)"></i> Entity Extraction & Knowledge Graph</h3></div>
                <div class="seo-card-body">
                    <div style="margin-bottom:12px;">
                        <select id="entityBlogSelect" class="seo-input seo-select">
                            <option value="">— Select a blog —</option>
                            <?php foreach ($blogs as $b): ?>
                            <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="seo-btn seo-btn-primary" onclick="runEntityExtract()"><i class="fas fa-sitemap"></i> Extract Entities</button>
                        <button class="seo-btn seo-btn-gold" onclick="runKnowledgeGraph()"><i class="fas fa-project-diagram"></i> Knowledge Graph</button>
                    </div>
                    <div id="entityResults" style="margin-top:16px;"></div>
                </div>
            </div>
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-copy" style="color:var(--seo-red)"></i> Semantic Duplicate Detection</h3></div>
                <div class="seo-card-body">
                    <p style="font-size:0.83rem;color:#64748b;margin-bottom:12px;">Uses vector embeddings to find semantically similar content across all blogs — even if they use different words.</p>
                    <button class="seo-btn seo-btn-primary" onclick="runDuplicateDetection()"><i class="fas fa-search"></i> Scan for Duplicates</button>
                    <div id="dupeResults" style="margin-top:16px;"></div>
                </div>
            </div>
        </div>
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-crosshairs" style="color:var(--seo-blue)"></i> Search Intent Classification</h3></div>
            <div class="seo-card-body">
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input type="text" id="intentKeyword" class="seo-input" placeholder="Enter keyword to classify intent..." style="flex:1;">
                    <button class="seo-btn seo-btn-primary" onclick="classifyIntent()"><i class="fas fa-brain"></i> Classify</button>
                </div>
                <div id="intentResults"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 10: TOPICAL AUTHORITY
         ============================================================ -->
    <div class="seo-panel" id="panel-authority">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-crown" style="color:var(--seo-gold)"></i> Topical Authority Analyzer</h3></div>
            <div class="seo-card-body">
                <div style="display:flex;gap:8px;margin-bottom:16px;">
                    <input type="text" id="authorityTopic" class="seo-input" placeholder="e.g. Kashmir Saffron, Olive Oil, Dry Fruits" style="flex:1;">
                    <button class="seo-btn seo-btn-gold" onclick="calcAuthority()"><i class="fas fa-crown"></i> Calculate</button>
                    <button class="seo-btn seo-btn-primary" onclick="findGaps()"><i class="fas fa-search-minus"></i> Find Gaps</button>
                </div>
                <div id="authorityResults"></div>
            </div>
        </div>
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-lightbulb" style="color:var(--seo-green)"></i> Content Opportunity Discovery</h3></div>
            <div class="seo-card-body">
                <p style="font-size:0.83rem;color:#64748b;margin-bottom:12px;">AI analyzes your existing content + products to discover high-value content opportunities you're missing.</p>
                <button class="seo-btn seo-btn-gold" onclick="discoverOpportunities()"><i class="fas fa-brain"></i> Discover Opportunities</button>
                <div id="opportunityResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 11: AI SEARCH OPTIMIZATION
         ============================================================ -->
    <div class="seo-panel" id="panel-aisearch">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-robot" style="color:var(--seo-purple)"></i> AI Overview + Featured Snippet + Voice Search Optimizer</h3></div>
            <div class="seo-card-body">
                <div class="seo-grid-2" style="margin-bottom:12px;">
                    <select id="aiSearchBlog" class="seo-input seo-select">
                        <option value="">— Select a blog —</option>
                        <?php foreach ($blogs as $b): ?>
                        <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="display:flex;gap:8px;">
                        <button class="seo-btn seo-btn-primary" onclick="optimizeAiSearch()"><i class="fas fa-robot"></i> Optimize</button>
                        <button class="seo-btn seo-btn-gold" onclick="generateSnippets()"><i class="fas fa-magic"></i> Generate Snippets</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="aiSearchResults" style="display:none;"></div>
        <div id="snippetResults" style="display:none;"></div>
    </div>

    <!-- ============================================================
         TAB 12: RANKING PREDICTIONS
         ============================================================ -->
    <div class="seo-panel" id="panel-ranking">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-trophy" style="color:var(--seo-gold)"></i> AI Ranking Probability Scoring</h3></div>
            <div class="seo-card-body">
                <div class="seo-grid-2" style="margin-bottom:12px;">
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Blog</label>
                        <select id="rankBlogSelect" class="seo-input seo-select">
                            <option value="">— Select —</option>
                            <?php foreach ($blogs as $b): ?>
                            <option value="<?= $b['id']; ?>"><?= htmlspecialchars($b['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;">Target Keyword (optional)</label>
                        <input type="text" id="rankKeyword" class="seo-input" placeholder="Leave blank to use blog's focus keyword">
                    </div>
                </div>
                <button class="seo-btn seo-btn-gold" onclick="predictRanking()"><i class="fas fa-brain"></i> Predict Ranking</button>
                <div id="rankResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 13: SERP INTELLIGENCE
         ============================================================ -->
    <div class="seo-panel" id="panel-serp">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-globe" style="color:var(--seo-blue)"></i> SERP Competitor Analysis (DataForSEO)</h3></div>
            <div class="seo-card-body">
                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <input type="text" id="serpKeyword" class="seo-input" placeholder="Enter keyword to analyze SERP..." style="flex:1;">
                    <button class="seo-btn seo-btn-primary" onclick="analyzeSERP()"><i class="fas fa-globe"></i> Analyze SERP</button>
                </div>
                <div id="serpResults"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 15: WHY NOT RANKING
         ============================================================ -->
    <div class="seo-panel" id="panel-whynotranking">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Why Is My Page Not Ranking?</h3></div>
            <div class="seo-card-body">
                <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">Select a blog post to diagnose exactly why it's not ranking on Google — with severity scores, ranking impact, and AI-powered fix suggestions.</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <select id="wnrBlog" class="seo-input seo-select" style="flex:1;min-width:200px;">
                        <option value="">— Select blog —</option>
                        <?php foreach($db->query("SELECT id, title FROM blogs WHERE status='published' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="seo-btn seo-btn-primary" onclick="diagnoseRanking()"><i class="fas fa-stethoscope"></i> Diagnose</button>
                </div>
                <div id="wnrResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 16: PRODUCT SEO ANALYZER
         ============================================================ -->
    <div class="seo-panel" id="panel-productanalyzer">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-box-open" style="color:var(--seo-blue);"></i> Deep Product SEO Analyzer</h3></div>
            <div class="seo-card-body">
                <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">Analyze any product page for SEO issues: buyer intent, CTR optimization, trust signals, schema readiness, transactional intent, and more.</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <select id="prodAnalyzeSel" class="seo-input seo-select" style="flex:1;min-width:200px;">
                        <option value="">— Select product —</option>
                        <?php try { foreach($db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; } catch(Exception $e) {} ?>
                    </select>
                    <button class="seo-btn seo-btn-primary" onclick="analyzeProduct()"><i class="fas fa-search"></i> Analyze Product</button>
                </div>
                <div id="prodAnalyzeResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 17: CORE WEB VITALS / PAGESPEED
         ============================================================ -->
    <div class="seo-panel" id="panel-pagespeed">
        <div class="seo-card">
            <div class="seo-card-header"><h3><i class="fas fa-tachometer-alt" style="color:var(--seo-green);"></i> Core Web Vitals — Google PageSpeed Insights</h3></div>
            <div class="seo-card-body">
                <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">Analyze LCP, CLS, TBT/INP, FCP scores using Google's free PageSpeed Insights API. No API key required.</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="psUrl" class="seo-input" placeholder="https://gilafstore.com" style="flex:1;min-width:220px;" value="https://gilafstore.com">
                    <select id="psStrategy" class="seo-input seo-select" style="width:120px;">
                        <option value="mobile">Mobile</option>
                        <option value="desktop">Desktop</option>
                    </select>
                    <button class="seo-btn seo-btn-primary" onclick="runPageSpeed()"><i class="fas fa-bolt"></i> Analyze</button>
                </div>
                <div id="psResults" style="margin-top:16px;"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 18: AI FIX ENGINE & SCHEMA GENERATOR
         ============================================================ -->
    <div class="seo-panel" id="panel-aitools">
        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-magic" style="color:var(--seo-purple);"></i> AI One-Click Fix Engine</h3></div>
                <div class="seo-card-body">
                    <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">Generate AI-powered fixes for specific SEO issues.</p>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Fix Type</label>
                        <select id="aiFixType" class="seo-input seo-select">
                            <option value="ai_rewrite_title">Rewrite Title for CTR</option>
                            <option value="ai_rewrite_meta">Rewrite Meta Title + Description</option>
                            <option value="ai_expand">Expand Content</option>
                            <option value="ai_add_faq">Generate FAQ Section</option>
                            <option value="ai_add_entities">Add Semantic Entities</option>
                            <option value="ai_add_eeat">Add E-E-A-T Signals</option>
                            <option value="ai_keyword_optimize">Keyword Optimization</option>
                            <option value="ai_add_links">Internal Link Suggestions</option>
                        </select>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Title</label>
                        <input type="text" id="aiFixTitle" class="seo-input" placeholder="Page title">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Keyword</label>
                        <input type="text" id="aiFixKeyword" class="seo-input" placeholder="Target keyword">
                    </div>
                    <button class="seo-btn seo-btn-primary" onclick="runAiFix()"><i class="fas fa-magic"></i> Generate Fix</button>
                    <div id="aiFixResults" style="margin-top:12px;"></div>
                </div>
            </div>
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-code" style="color:var(--seo-gold);"></i> AI Schema JSON-LD Generator</h3></div>
                <div class="seo-card-body">
                    <p style="font-size:0.85rem;color:#64748b;margin-bottom:12px;">Generate structured data markup for rich results in Google. Auto-detects schema type from content.</p>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Schema Type</label>
                        <select id="schemaType" class="seo-input seo-select">
                            <option value="auto">Auto Detect</option>
                            <option value="product">Product</option>
                            <option value="article">Article / Blog Post</option>
                            <option value="faq">FAQ Page</option>
                            <option value="breadcrumb">Breadcrumb</option>
                            <option value="howto">HowTo / Guide</option>
                            <option value="organization">Organization</option>
                            <option value="collectionpage">CollectionPage (Category)</option>
                        </select>
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Select Content</label>
                        <select id="schemaBlog" class="seo-input seo-select" onchange="autoDetectSchemaType()">
                            <option value="">— Select blog/product —</option>
                            <?php foreach($db->query("SELECT id, title FROM blogs WHERE status='published' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) as $b): ?>
                            <option value="blog_<?= $b['id'] ?>"><?= htmlspecialchars($b['title']) ?></option>
                            <?php endforeach; ?>
                            <?php try { foreach($db->query("SELECT id, name FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) as $p): ?>
                            <option value="prod_<?= $p['id'] ?>">[Product] <?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; } catch(Exception $e) {} ?>
                        </select>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button class="seo-btn seo-btn-gold" onclick="generateSchema()"><i class="fas fa-code"></i> Generate Schema</button>
                        <button class="seo-btn seo-btn-primary" onclick="validateSchema()" id="btnValidateSchema" style="display:none;"><i class="fas fa-check-circle"></i> Validate</button>
                        <button class="seo-btn" onclick="applySchema()" id="btnApplySchema" style="display:none;background:#22c55e;color:#fff;"><i class="fas fa-bolt"></i> Auto Apply</button>
                    </div>
                    <div id="schemaResults" style="margin-top:12px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         TAB 14: API CENTER
         ============================================================ -->
    <div class="seo-panel" id="panel-apicenter">

        <!-- AI PING CARD -->
        <div class="seo-card" style="margin-bottom:20px;">
            <div class="seo-card-header">
                <h3><i class="fas fa-satellite-dish" style="color:#8b5cf6;"></i> AI Connection Status</h3>
                <div style="display:flex;gap:8px;">
                    <button class="seo-btn seo-btn-sm" style="background:#8b5cf6;color:#fff;" onclick="pingAI()"><i class="fas fa-satellite-dish"></i> Ping AI</button>
                    <a href="chatbot_settings.php" class="seo-btn seo-btn-outline seo-btn-sm"><i class="fas fa-cog"></i> AI Settings</a>
                </div>
            </div>
            <div class="seo-card-body" id="aiPingResult">
                <div style="text-align:center;padding:16px;color:#94a3b8;font-size:0.85rem;">Click <strong>Ping AI</strong> to auto-detect and test the configured AI provider</div>
            </div>
        </div>

        <!-- API ENDPOINT TESTS -->
        <div class="seo-card" style="margin-bottom:20px;">
            <div class="seo-card-header">
                <h3><i class="fas fa-vial" style="color:var(--seo-green);"></i> API Endpoint Tests</h3>
                <div style="display:flex;gap:8px;">
                    <button class="seo-btn seo-btn-sm seo-btn-primary" onclick="runAllApiTests()"><i class="fas fa-play"></i> Test All</button>
                </div>
            </div>
            <div class="seo-card-body">
                <div id="apiTestGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px;">
                </div>
                <div id="apiTestSummary" style="margin-top:14px;display:none;padding:12px 16px;background:#f8fafc;border-radius:8px;font-size:0.82rem;"></div>
            </div>
        </div>

        <!-- EXISTING CONFIG / STATS -->
        <div class="seo-grid-2">
            <div class="seo-card">
                <div class="seo-card-header"><h3><i class="fas fa-cog" style="color:var(--seo-dark)"></i> API Configuration</h3></div>
                <div class="seo-card-body">
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Embedding Provider</label>
                        <select id="cfgEmbeddingProvider" class="seo-input seo-select">
                            <option value="gemini">Gemini (Google AI)</option>
                            <option value="openai">OpenAI</option>
                            <option value="claude">Claude (Anthropic)</option>
                            <option value="huggingface">HuggingFace Inference</option>
                        </select>
                        <div id="cfgProviderHint" style="font-size:0.72rem;color:#94a3b8;margin-top:4px;"></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Qdrant URL</label>
                        <input type="text" id="cfgQdrantUrl" class="seo-input" placeholder="http://localhost:6333">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">Qdrant API Key</label>
                        <input type="password" id="cfgQdrantKey" class="seo-input" placeholder="Optional for local instances">
                    </div>
                    <div style="margin-bottom:12px;">
                        <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="testQdrant()" style="width:100%;background:#7c3aed10;border-color:#7c3aed40;color:#7c3aed;"><i class="fas fa-database"></i> Test Qdrant Connection</button>
                        <div id="qdrantTestResult" style="margin-top:8px;font-size:0.8rem;"></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">HuggingFace API Key</label>
                        <input type="password" id="cfgHfKey" class="seo-input" placeholder="For HF embedding provider">
                    </div>
                    <div style="margin-bottom:12px;">
                        <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="testHuggingFace()" style="width:100%;background:#ff990010;border-color:#ff990040;color:#ff9900;"><i class="fas fa-fire"></i> Test HuggingFace Connection</button>
                        <div id="hfTestResult" style="margin-top:8px;font-size:0.8rem;"></div>
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">DataForSEO Login</label>
                        <input type="text" id="cfgDfsLogin" class="seo-input" placeholder="DataForSEO email">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="font-size:0.82rem;font-weight:600;color:#475569;display:block;margin-bottom:4px;">DataForSEO Password</label>
                        <input type="password" id="cfgDfsPass" class="seo-input" placeholder="DataForSEO API password">
                    </div>
                    <div style="margin-bottom:12px;">
                        <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="testDataForSeo()" style="width:100%;"><i class="fas fa-plug"></i> Test DataForSEO Connection</button>
                        <div id="dfsTestResult" style="margin-top:8px;font-size:0.8rem;"></div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="seo-btn seo-btn-primary" onclick="saveApiSettings()"><i class="fas fa-save"></i> Save Settings</button>
                        <button class="seo-btn seo-btn-outline" onclick="loadApiSettings()"><i class="fas fa-sync"></i> Reload</button>
                    </div>
                </div>
            </div>
            <div>
                <div class="seo-card">
                    <div class="seo-card-header"><h3><i class="fas fa-chart-bar" style="color:var(--seo-green)"></i> Token & Cost Tracker</h3></div>
                    <div class="seo-card-body" id="tokenStats">
                        <button class="seo-btn seo-btn-outline seo-btn-sm" onclick="loadApiStats()"><i class="fas fa-sync"></i> Load Stats</button>
                    </div>
                </div>
                <div class="seo-card">
                    <div class="seo-card-header"><h3><i class="fas fa-database" style="color:var(--seo-purple)"></i> Embedding Cache</h3></div>
                    <div class="seo-card-body" id="cacheStats">
                        <p style="color:#94a3b8;font-size:0.83rem;">Load stats to see cache info</p>
                    </div>
                </div>
                <div class="seo-card">
                    <div class="seo-card-header"><h3><i class="fas fa-vector-square" style="color:var(--seo-blue)"></i> Vector Index</h3></div>
                    <div class="seo-card-body">
                        <p style="font-size:0.83rem;color:#64748b;margin-bottom:10px;">Index all published blogs into vector database for semantic search.</p>
                        <button class="seo-btn seo-btn-gold" onclick="indexAllContent()"><i class="fas fa-upload"></i> Index All Content</button>
                        <div id="indexResults" style="margin-top:10px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const API = '<?= base_url("admin/seo_api.php"); ?>';

// ============================================================
// TAB SWITCHING
// ============================================================
document.querySelectorAll('.seo-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.seo-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.seo-panel').forEach(p => p.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
        // Auto-load API Center data on first visit
        if (tab.dataset.tab === 'apicenter' && typeof _apiSettingsLoaded !== 'undefined' && !_apiSettingsLoaded) {
            loadApiSettings();
            loadApiStats();
        }
    });
});

// ============================================================
// API HELPER
// ============================================================
async function seoApi(action, data = {}) {
    const resp = await fetch(API, {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({action, ...data})
    });
    return resp.json();
}

function scoreColor(s) { return s >= 70 ? 'green' : s >= 40 ? 'yellow' : 'red'; }
function scoreIcon(status) {
    if (status === 'pass') return '<i class="fas fa-check-circle pass"></i>';
    if (status === 'warn') return '<i class="fas fa-exclamation-circle warn"></i>';
    return '<i class="fas fa-times-circle fail"></i>';
}
function escHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

// ============================================================
// OVERVIEW — Auto-load
// ============================================================
(async function loadOverview() {
    try {
        const stats = await seoApi('site_stats');
        if (stats.success) {
            const s = stats.stats;
            document.getElementById('statBlogs').textContent = s.published_blogs || 0;
            document.getElementById('statProducts').textContent = s.total_products || 0;
            document.getElementById('statMetaTitles').textContent = s.blogs_with_meta_title || 0;
            document.getElementById('statMetaDescs').textContent = s.blogs_with_meta_desc || 0;
            document.getElementById('statKeywords').textContent = s.blogs_with_keywords || 0;
            document.getElementById('statImages').textContent = s.blogs_with_images || 0;
            document.getElementById('statFaqs').textContent = s.total_faqs || 0;
            document.getElementById('statCategories').textContent = s.total_categories || 0;
        }
    } catch(e) { console.error('Stats error:', e); }

    // Quick scan
    try {
        const scan = await seoApi('scan_blogs');
        if (scan.success) {
            const r = scan.results;
            let html = `<div style="display:flex;gap:16px;margin-bottom:12px;">
                <div><strong style="color:var(--seo-red);">${r.total_errors}</strong> <small>errors</small></div>
                <div><strong style="color:var(--seo-yellow);">${r.total_warnings}</strong> <small>warnings</small></div>
                <div><strong>${r.blogs_with_issues}/${r.total_blogs}</strong> <small>blogs with issues</small></div>
            </div>`;
            r.items.slice(0, 5).forEach(item => {
                html += `<div style="padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:0.83rem;">
                    <strong>${escHtml(item.title)}</strong>
                    <div style="margin-top:4px;">${item.issues.map(i => `<span class="issue-badge ${i.type}">${escHtml(i.text)}</span> `).join('')}</div>
                </div>`;
            });
            if (r.items.length > 5) html += `<p style="font-size:0.8rem;color:#94a3b8;margin-top:8px;">+ ${r.items.length - 5} more — check Technical SEO tab</p>`;
            document.getElementById('quickScanResults').innerHTML = html;
        }
    } catch(e) { document.getElementById('quickScanResults').innerHTML = '<p style="color:#94a3b8;">Scan failed</p>'; }

    // Quick link opportunities (with timeout to prevent overview from getting stuck)
    try {
        const linkCtrl = new AbortController();
        const linkTimeout = setTimeout(() => linkCtrl.abort(), 30000);
        const linkRaw = await fetch(API, {
            method: 'POST', headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({action:'link_opportunities'}), signal: linkCtrl.signal
        });
        clearTimeout(linkTimeout);
        const links = await linkRaw.json();
        if (links.success) {
            const opps = links.opportunities || [];
            const st = links.stats || {};
            let html = `<div style="display:flex;gap:12px;margin-bottom:8px;">
                <div><strong style="color:var(--seo-blue);">${opps.length}</strong> <small>link opportunities</small></div>
                ${st.total_cannibalized ? `<div><strong style="color:var(--seo-red);">${st.total_cannibalized}</strong> <small>cannibalized</small></div>` : ''}
                ${st.total_orphans ? `<div><strong style="color:#8b5cf6;">${st.total_orphans}</strong> <small>orphans</small></div>` : ''}
            </div>`;
            opps.slice(0, 5).forEach(o => {
                const sc = o.scores || {};
                html += `<div style="padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:0.83rem;">
                    <div><i class="fas fa-arrow-right" style="color:var(--seo-gold);font-size:0.7rem;margin-right:6px;"></i>
                    <strong>${escHtml(o.source_title)}</strong> → <span style="color:var(--seo-blue);">${escHtml(o.target_title)}</span>
                    <span style="font-size:0.7rem;color:#94a3b8;margin-left:6px;"><i class="fas fa-star" style="color:var(--seo-gold);"></i> ${Math.round((sc.total||0)*100)}%</span></div>
                    <small style="color:#94a3b8;">${escHtml(o.reason)}</small>
                </div>`;
            });
            if (opps.length > 5) html += `<p style="font-size:0.8rem;color:#94a3b8;margin-top:8px;">+ ${opps.length - 5} more — check Internal Linking tab</p>`;
            if (opps.length === 0) html = '<p style="color:var(--seo-green);"><i class="fas fa-check-circle"></i> All mentioned content is properly linked!</p>';
            document.getElementById('quickLinkResults').innerHTML = html;
        } else {
            document.getElementById('quickLinkResults').innerHTML = '<p style="color:#94a3b8;">'+escHtml(links.message||'Scan failed')+'</p>';
        }
    } catch(e) {
        document.getElementById('quickLinkResults').innerHTML = e.name === 'AbortError'
            ? '<p style="color:#94a3b8;">Timed out — click Internal Linking tab to scan</p>'
            : '<p style="color:#94a3b8;">Scan failed</p>';
    }
})();

// ============================================================
// CONTENT ANALYZER
// ============================================================
async function runBlogAnalysis() {
    const blogId = document.getElementById('analyzerBlogSelect').value;
    if (!blogId) { alert('Select a blog first'); return; }

    document.getElementById('analyzerResults').style.display = 'block';
    document.getElementById('detailedAnalysisSection').style.display = 'none';
    document.getElementById('analyzerChecks').innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Analyzing...</p></div>';

    const resp = await seoApi('analyze_blog', {blog_id: parseInt(blogId)});
    if (!resp.success) { document.getElementById('analyzerChecks').innerHTML = '<p style="color:red;">'+escHtml(resp.message)+'</p>'; return; }

    const r = resp.results;
    const overall = r.overall_score;
    const circle = document.getElementById('overallScoreCircle');
    circle.className = 'score-circle ' + scoreColor(overall);
    document.getElementById('overallScoreVal').textContent = overall;
    document.getElementById('overallScoreLabel').textContent = r.blog ? escHtml(r.blog.title) + ' — ' + r.blog.word_count + ' words' : '';
    window._seoOverallScore = overall;

    // Module scores grid
    const modules = ['basic_seo','keyword','readability','content_quality','heading_structure','eeat','image_seo','internal_links','ai_search','semantic','schema'];
    let gridHtml = '';
    modules.forEach(m => {
        if (!r[m]) return;
        const s = r[m].score;
        gridHtml += `<div style="background:#fafbfc;border-radius:8px;padding:10px;text-align:center;">
            <div style="font-size:1.2rem;font-weight:800;color:${s>=70?'var(--seo-green)':s>=40?'var(--seo-yellow)':'var(--seo-red)'};">${s}%</div>
            <div style="font-size:0.72rem;color:#94a3b8;">${r[m].label || m}</div>
        </div>`;
    });
    document.getElementById('moduleScoresGrid').innerHTML = gridHtml;

    // Detailed checks — each item triggers deep analysis expand on click
    let checksHtml = '';
    modules.forEach(m => {
        if (!r[m] || !r[m].checks) return;
        const s = r[m].score;
        checksHtml += `<div class="seo-card">
            <div class="seo-card-header" onclick="this.parentElement.classList.toggle('collapsed')">
                <h3><span class="card-score ${scoreColor(s)}">${s}%</span> ${r[m].label || m}</h3>
                <i class="fas fa-chevron-down" style="color:#94a3b8;"></i>
            </div>
            <div class="seo-card-body">
                ${r[m].checks.map(c => `<div class="seo-check">${scoreIcon(c.status)}<span>${escHtml(c.text)}</span></div>`).join('')}
            </div>
        </div>`;
    });
    document.getElementById('analyzerChecks').innerHTML = checksHtml;

    // Run detailed analysis
    runDetailedAnalysis(parseInt(blogId));
}

// ============================================================
// V5: DETAILED SEO ANALYSIS ENGINE
// ============================================================
async function runDetailedAnalysis(blogId) {
    const section = document.getElementById('detailedAnalysisSection');
    section.style.display = 'block';
    section.innerHTML = `<div class="da-section-header">
        <h2><i class="fas fa-microscope"></i> Detailed SEO Analysis <small style="font-size:0.6em;opacity:0.7;font-weight:400;margin-left:8px;">v5 Intelligence Engine</small></h2>
        <span style="font-size:0.78rem;opacity:0.7;"><i class="fas fa-spinner fa-spin"></i> Deep scanning...</span>
    </div>
    <div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Running deep content analysis...</p></div>`;

    try {
        const resp = await seoApi('v5_detailed_analysis', {blog_id: blogId});
        if (!resp.success) {
            section.innerHTML = `<div class="da-section-header"><h2><i class="fas fa-microscope"></i> Detailed SEO Analysis</h2></div>
                <p style="color:var(--seo-red);padding:16px;">${escHtml(resp.message||'Analysis failed')}</p>`;
            return;
        }
        renderDetailedAnalysis(resp.data, blogId);
    } catch(e) {
        section.innerHTML += `<p style="color:var(--seo-red);padding:16px;">Error: ${escHtml(e.message)}</p>`;
    }
}

function renderDetailedAnalysis(data, blogId) {
    const section = document.getElementById('detailedAnalysisSection');
    const issues  = data.issues || [];
    const rb       = data.readability || {};

    // Cache issues for the Smart Fix Engine
    cacheIssuesForFix(issues, blogId);

    const sevColors = {critical:'#ef4444', warning:'#eab308', moderate:'#3b82f6', good:'#22c55e'};
    const impactLabels = {high:'🔴 High SEO Impact', medium:'🟡 Medium SEO Impact', low:'🟢 Low SEO Impact'};
    const priorityLabels = ['', '🔥 Fix This First', '⚡ Recommended Next', '💡 Optional Optimization'];
    const priorityCls    = ['', 'p1', 'p2', 'p3'];

    // ── Header bar ──
    let html = `<div class="da-section-header">
        <h2><i class="fas fa-microscope"></i> Detailed SEO Analysis <small style="font-size:0.6em;opacity:0.7;font-weight:400;margin-left:8px;">v5 Intelligence Engine</small></h2>
        <div class="da-summary-pills">
            ${data.critical  > 0 ? `<span class="da-pill critical"><i class="fas fa-times-circle"></i> ${data.critical} Critical</span>` : ''}
            ${data.warnings  > 0 ? `<span class="da-pill warning"><i class="fas fa-exclamation-triangle"></i> ${data.warnings} Warnings</span>` : ''}
            ${data.moderate  > 0 ? `<span class="da-pill moderate"><i class="fas fa-info-circle"></i> ${data.moderate} Moderate</span>` : ''}
            ${issues.length === 0 ? `<span class="da-pill good"><i class="fas fa-check-circle"></i> No Issues Found</span>` : ''}
        </div>
    </div>`;

    // ── Readability Breakdown Panel ──
    if (rb.flesch_score !== undefined) {
        const fleschColor = rb.flesch_score >= 60 ? 'var(--seo-green)' : rb.flesch_score >= 40 ? 'var(--seo-yellow)' : 'var(--seo-red)';
        html += `<div class="seo-card" style="margin-bottom:16px;">
            <div class="seo-card-header" onclick="this.parentElement.classList.toggle('collapsed')">
                <h3><i class="fas fa-chart-bar" style="color:var(--seo-purple);"></i> Readability Intelligence Report</h3>
                <i class="fas fa-chevron-down" style="color:#94a3b8;"></i>
            </div>
            <div class="seo-card-body">
                <div style="margin-bottom:12px;padding:10px 14px;background:linear-gradient(135deg,#f8fafc,#f0f9ff);border-radius:10px;border:1px solid #e0e7ff;">
                    <div style="font-size:0.78rem;color:#64748b;margin-bottom:4px;font-weight:600;">FLESCH READING EASE</div>
                    <div style="display:flex;align-items:center;gap:16px;">
                        <div style="font-size:2rem;font-weight:800;color:${fleschColor};">${rb.flesch_score}</div>
                        <div>
                            <div style="font-size:0.85rem;font-weight:600;color:#1e293b;">${rb.reading_level||'N/A'}</div>
                            <div style="font-size:0.75rem;color:#94a3b8;">Target: 60+ for best rankings</div>
                        </div>
                        <div style="flex:1;margin-left:12px;">
                            <div style="height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:${Math.min(100,rb.flesch_score)}%;background:${fleschColor};border-radius:4px;transition:width 0.8s;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="da-readability-grid">
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:${rb.avg_sentence_length<=20?'var(--seo-green)':'var(--seo-red)'};">${rb.avg_sentence_length}</div>
                        <div class="da-read-label">Avg Sentence</div>
                        <div class="da-read-sub">words (≤20 ideal)</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:${rb.complex_word_pct<=20?'var(--seo-green)':'var(--seo-yellow)'};">${rb.complex_word_pct}%</div>
                        <div class="da-read-label">Complex Words</div>
                        <div class="da-read-sub">3+ syllables</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:${rb.passive_pct<=10?'var(--seo-green)':'var(--seo-red)'};">${rb.passive_pct}%</div>
                        <div class="da-read-label">Passive Voice</div>
                        <div class="da-read-sub">≤10% ideal</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:${rb.transition_pct>=30?'var(--seo-green)':'var(--seo-yellow)'};">${rb.transition_pct}%</div>
                        <div class="da-read-label">Transition Words</div>
                        <div class="da-read-sub">30%+ ideal</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:${rb.long_sentence_pct<=15?'var(--seo-green)':'var(--seo-red)'};">${rb.long_sentence_pct}%</div>
                        <div class="da-read-label">Long Sentences</div>
                        <div class="da-read-sub">≤15% ideal</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:var(--seo-blue);">${rb.paragraph_count||0}</div>
                        <div class="da-read-label">Paragraphs</div>
                        <div class="da-read-sub">${rb.sentence_count||0} sentences</div>
                    </div>
                    <div class="da-read-metric">
                        <div class="da-read-val" style="color:var(--seo-purple);">${data.word_count||0}</div>
                        <div class="da-read-label">Total Words</div>
                        <div class="da-read-sub">1500+ ideal</div>
                    </div>
                </div>
            </div>
        </div>`;
    }

    // ── No issues found ──
    if (issues.length === 0) {
        html += `<div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:12px;padding:30px;text-align:center;margin-top:8px;">
            <i class="fas fa-check-circle" style="font-size:2.5rem;color:var(--seo-green);margin-bottom:10px;display:block;"></i>
            <h3 style="color:#166534;margin:0 0 6px;">Excellent! No Major Issues Detected</h3>
            <p style="color:#16a34a;margin:0;font-size:0.88rem;">Your content passes all detailed SEO quality checks.</p>
        </div>`;
        section.innerHTML = html;
        return;
    }

    // ── Smart Fix Toolbar ──
    html += `<div class="seo-fix-toolbar" id="seoFixToolbar">
        <select class="fix-mode-select" id="fixModeSelect">
            <option value="smart">Smart Fix</option>
            <option value="quick">Quick Fix</option>
            <option value="deep">Deep SEO Optimization</option>
            <option value="humanized">Humanized Rewrite</option>
            <option value="semantic">Semantic Expansion</option>
            <option value="eeat">E-E-A-T Optimization</option>
        </select>
        <button class="fix-btn fix-btn-primary" onclick="seoBulkFix('${blogId}','critical')"><i class="fas fa-bolt"></i> Fix Critical</button>
        <button class="fix-btn fix-btn-success" onclick="seoBulkFix('${blogId}','readability')"><i class="fas fa-book-reader"></i> Fix Readability</button>
        <button class="fix-btn fix-btn-warning" onclick="seoBulkFix('${blogId}','semantic')"><i class="fas fa-brain"></i> Fix Semantic</button>
        <button class="fix-btn fix-btn-outline" onclick="seoBulkFix('${blogId}','thin_content')"><i class="fas fa-expand-arrows-alt"></i> Fix Thin Content</button>
        <button class="fix-btn fix-btn-outline" onclick="seoBulkFix('${blogId}','linking')"><i class="fas fa-link"></i> Fix Links</button>
        <button class="fix-btn fix-btn-outline" onclick="seoBulkFix('${blogId}','image_seo')"><i class="fas fa-image"></i> Fix Images</button>
        <button class="fix-btn fix-btn-danger" onclick="seoViewFixLog('${blogId}')"><i class="fas fa-history"></i> Fix Log</button>
    </div>
    <div id="bulkFixResults"></div>
    <div id="fixLogPanel"></div>`;

    // ── Issue Accordions ──
    html += `<div style="margin-bottom:8px;font-size:0.8rem;color:#64748b;">
        <i class="fas fa-info-circle" style="color:var(--seo-gold);"></i>
        Click any issue below to expand full analysis with exact locations, fix suggestions, and before/after previews.
    </div>`;

    issues.forEach((issue, idx) => {
        // Special premium renderer for thin paragraphs
        if (issue.id === 'thin_paragraphs') {
            html += renderThinParagraphsPanel(issue, blogId);
            return;
        }
        const sev  = issue.severity || 'moderate';
        const imp  = issue.seo_impact || 'medium';
        const prio = Math.min(3, Math.max(1, issue.priority || 3));
        const expl = issue.explanation || {};
        const locs = issue.locations || [];
        const fixes= issue.fix_suggestions || [];
        const ba   = issue.before_after || {};
        const aiSug= issue.ai_suggestions || [];

        html += `<div class="da-issue" id="da-issue-${idx}">
            <div class="da-issue-header" onclick="daToggle(${idx})">
                <div class="da-severity-dot da-sev-${sev}"></div>
                <div class="da-issue-title">${escHtml(issue.title||'Issue')}</div>
                <div class="da-badges">
                    <span class="da-badge da-badge-module"><i class="fas fa-layer-group"></i> ${escHtml(issue.module||'')}</span>
                    <span class="da-badge da-badge-impact-${imp}"><i class="fas fa-bolt"></i> ${imp.charAt(0).toUpperCase()+imp.slice(1)} Impact</span>
                    ${issue.score_gain ? `<span class="da-badge da-badge-gain"><i class="fas fa-arrow-up"></i> +${issue.score_gain} pts</span>` : ''}
                    <span class="da-badge" style="background:rgba(${sev==='critical'?'239,68,68':sev==='warning'?'234,179,8':'59,130,246'},0.1);color:${sevColors[sev]};text-transform:capitalize;">${sev}</span>
                </div>
                <i class="fas fa-chevron-down da-chevron"></i>
            </div>
            <div class="da-issue-body">
                <div class="da-body-inner">

                    <!-- Meta Bar -->
                    <div class="da-meta-bar">
                        <div class="da-meta-item">
                            <div class="da-meta-label">Severity</div>
                            <div class="da-meta-value" style="color:${sevColors[sev]};text-transform:capitalize;">${sev}</div>
                        </div>
                        <div class="da-meta-item">
                            <div class="da-meta-label">Module</div>
                            <div class="da-meta-value">${escHtml(issue.module||'')}</div>
                        </div>
                        <div class="da-meta-item">
                            <div class="da-meta-label">Priority</div>
                            <div class="da-meta-value">#${prio}</div>
                        </div>
                        <div class="da-meta-item">
                            <div class="da-meta-label">Score Gain</div>
                            <div class="da-meta-value" style="color:var(--seo-green);">+${issue.score_gain||0} pts</div>
                        </div>
                        <div class="da-meta-item" style="flex:1;min-width:150px;">
                            <div class="da-meta-label">SEO Impact Meter</div>
                            <div class="da-impact-meter da-meter-${imp}" style="margin-top:4px;">
                                <div class="da-meter-bar"><div class="da-meter-fill"></div></div>
                                <span style="font-size:0.72rem;font-weight:700;color:${imp==='high'?'#dc2626':imp==='medium'?'#ca8a04':'#16a34a'};">${imp.toUpperCase()}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Explanation -->
                    ${expl.what||expl.why ? `<div class="da-section">
                        <div class="da-section-label"><i class="fas fa-question-circle" style="color:var(--seo-purple);"></i> Problem Explanation</div>
                        <div class="da-explanation">
                            ${expl.what ? `<div class="da-expl-row"><div class="da-expl-key">WHAT</div><div class="da-expl-val">${escHtml(expl.what)}</div></div>` : ''}
                            ${expl.why  ? `<div class="da-expl-row"><div class="da-expl-key">WHY</div><div class="da-expl-val">${escHtml(expl.why)}</div></div>` : ''}
                            ${expl.how  ? `<div class="da-expl-row"><div class="da-expl-key">HOW</div><div class="da-expl-val">${escHtml(expl.how)}</div></div>` : ''}
                            ${expl.ux   ? `<div class="da-expl-row"><div class="da-expl-key">UX</div><div class="da-expl-val">${escHtml(expl.ux)}</div></div>` : ''}
                        </div>
                    </div>` : ''}

                    <!-- Exact Locations -->
                    ${locs.length > 0 ? `<div class="da-section">
                        <div class="da-section-label"><i class="fas fa-map-marker-alt" style="color:#f97316;"></i> Exact Location in Content</div>
                        ${locs.map(loc => {
                            let locHtml = '<div class="da-location">';
                            if (loc.section)       locHtml += `<div class="da-loc-section"><i class="fas fa-heading" style="margin-right:5px;"></i>Section: "${escHtml(loc.section)}"</div>`;
                            if (loc.paragraph_num) locHtml += `<div class="da-loc-meta">📄 Paragraph ${loc.paragraph_num}${loc.sentence_num ? ` · Sentence ${loc.sentence_num}` : ''}${loc.word_count ? ` · ${loc.word_count} words` : ''}${loc.occurrences ? ` · ${loc.occurrences} occurrences` : ''}</div>`;
                            if (loc.phrase)        locHtml += `<div class="da-loc-meta">🔁 Repeated phrase: <strong>"${escHtml(loc.phrase)}"</strong> × ${loc.occurrences||''}</div>`;
                            if (loc.text)          locHtml += `<div class="da-loc-text" style="margin-top:6px;padding:6px 10px;background:rgba(239,68,68,0.05);border-left:2px solid #fca5a5;border-radius:4px;">"${escHtml(loc.text.substring(0,180))}${loc.text.length>180?'…':''}"</div>`;
                            if (loc.note)          locHtml += `<div class="da-loc-meta" style="margin-top:4px;"><i class="fas fa-info-circle"></i> ${escHtml(loc.note)}</div>`;
                            locHtml += '</div>';
                            return locHtml;
                        }).join('')}
                    </div>` : ''}

                    <!-- Fix Suggestions -->
                    ${fixes.length > 0 ? `<div class="da-section">
                        <div class="da-section-label"><i class="fas fa-tools" style="color:var(--seo-green);"></i> Smart Fix Suggestions</div>
                        <ul class="da-fix-list">
                            ${fixes.map((f, fi) => `<li class="da-fix-item"><div class="da-fix-num">${fi+1}</div><div>${escHtml(f)}</div></li>`).join('')}
                        </ul>
                    </div>` : ''}

                    <!-- Before / After -->
                    ${ba.before||ba.after ? `<div class="da-section">
                        <div class="da-section-label"><i class="fas fa-exchange-alt" style="color:var(--seo-gold);"></i> Before &amp; After Preview</div>
                        <div class="da-before-after">
                            <div class="da-before">
                                <div class="da-ba-label">🔴 BEFORE — Problematic</div>
                                ${escHtml(ba.before||'')}
                            </div>
                            <div class="da-after">
                                <div class="da-ba-label">✅ AFTER — Optimized</div>
                                ${escHtml(ba.after||'')}
                            </div>
                        </div>
                    </div>` : ''}

                    <!-- Priority Order -->
                    <div class="da-section">
                        <div class="da-section-label"><i class="fas fa-sort-amount-up" style="color:var(--seo-blue);"></i> Fix Priority Order</div>
                        <div class="da-priority-list">
                            <div class="da-priority-item ${priorityCls[prio]}">
                                <div class="da-priority-num">${prio}</div>
                                <div>${priorityLabels[prio]||'Optimization'} — ${escHtml(issue.title)}</div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Suggestions -->
                    ${aiSug.length > 0 ? `<div class="da-section">
                        <div class="da-section-label"><i class="fas fa-robot" style="color:var(--seo-purple);"></i> AI Optimization Suggestions</div>
                        <ul class="da-ai-list">
                            ${aiSug.map(s => `<li class="da-ai-item"><i class="fas fa-lightbulb"></i><span>${escHtml(s)}</span></li>`).join('')}
                        </ul>
                    </div>` : ''}

                    <!-- Action Buttons -->
                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
                        <button class="da-goto-btn" style="background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;border:none;" onclick="seoFixPreview(${idx}, '${blogId}')">
                            <i class="fas fa-magic"></i> AI Smart Fix
                        </button>
                        <a href="blog_edit.php?id=${blogId}" class="da-goto-btn" target="_blank">
                            <i class="fas fa-edit"></i> Go to Blog Editor
                        </a>
                        <button class="da-goto-btn gold" onclick="scrollToAnalyzerChecks()">
                            <i class="fas fa-arrow-up"></i> Back to Score Cards
                        </button>
                    </div>
                    <!-- Fix Preview Container -->
                    <div id="fix-preview-${idx}" style="position:relative;"></div>

                </div>
            </div>
        </div>`;
    });

    section.innerHTML = html;

    // Populate score prediction for thin paragraphs panel
    const tpIssue = issues.find(i => i.id === 'thin_paragraphs');
    if (tpIssue) {
        tpPopulateScore(window._seoOverallScore || 0, tpIssue.score_gain || 8);
    }
}

function daToggle(idx) {
    const el = document.getElementById('da-issue-' + idx);
    if (!el) return;
    const isOpen = el.classList.contains('da-open');
    // Close all others
    document.querySelectorAll('.da-issue.da-open').forEach(e => e.classList.remove('da-open'));
    if (!isOpen) {
        el.classList.add('da-open');
        setTimeout(() => el.scrollIntoView({behavior: 'smooth', block: 'nearest'}), 50);
    }
}

function scrollToAnalyzerChecks() {
    const el = document.getElementById('analyzerChecks');
    if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
}

// ============================================================
// SMART SEO AUTO FIX ENGINE
// ============================================================
window._seoIssuesCache = [];
window._seoBlogIdCache = '';

function cacheIssuesForFix(issues, blogId) {
    window._seoIssuesCache = issues;
    window._seoBlogIdCache = blogId;
}

function getFixMode() {
    return document.getElementById('fixModeSelect')?.value || 'smart';
}

async function seoFixPreview(issueIdx, blogId) {
    const issue = window._seoIssuesCache[issueIdx];
    if (!issue) { alert('Issue data not found'); return; }
    
    const container = document.getElementById('fix-preview-' + issueIdx);
    if (!container) return;
    
    const mode = getFixMode();
    
    container.innerHTML = `<div class="fix-progress-overlay" style="position:relative;padding:30px;">
        <div class="spinner"></div>
        <p>Generating AI fix preview (${mode} mode)...</p>
    </div>`;
    
    const resp = await seoApi('v5_fix_preview', { blog_id: parseInt(blogId), issue: issue, mode: mode });
    
    if (!resp.success) {
        container.innerHTML = `<div class="fix-preview-panel">
            <div style="padding:14px 16px;color:#dc2626;font-size:0.82rem;">
                <i class="fas fa-exclamation-triangle"></i> ${escHtml(resp.message || 'Fix generation failed')}
                ${resp.violations ? '<br><small style="color:#64748b;">Violations: ' + resp.violations.join(', ') + '</small>' : ''}
            </div>
        </div>`;
        return;
    }
    
    const conf = resp.ai_confidence || 0.85;
    const confClass = conf >= 0.8 ? 'confidence-high' : conf >= 0.6 ? 'confidence-med' : 'confidence-low';
    const confLabel = conf >= 0.8 ? 'High Confidence' : conf >= 0.6 ? 'Medium' : 'Low';
    
    const m = resp.metrics || {};
    const sd = resp.score_diff || {};
    const original = resp.original || '';
    const fixed = resp.fixed || '';
    const changes = resp.changes_summary || [];
    
    // Build REAL score metrics display
    let metricsHtml = '';
    if (m.flesch_before !== undefined && m.flesch_after !== undefined) {
        const fGain = (m.flesch_after - m.flesch_before).toFixed(1);
        const fCls = fGain > 0 ? 'positive' : fGain < 0 ? 'negative' : 'neutral';
        metricsHtml += `<div class="fix-metric ${fCls}"><i class="fas fa-book-reader"></i> Flesch: ${m.flesch_before} → ${m.flesch_after} (${fGain > 0 ? '+' : ''}${fGain})</div>`;
    }
    if (m.transition_before !== undefined && m.transition_after !== undefined && m.transition_after !== m.transition_before) {
        metricsHtml += `<div class="fix-metric positive"><i class="fas fa-link"></i> Transitions: ${m.transition_before}% → ${m.transition_after}%</div>`;
    }
    if (m.passive_before !== undefined && m.passive_after !== undefined && m.passive_after < m.passive_before) {
        metricsHtml += `<div class="fix-metric positive"><i class="fas fa-pen"></i> Passive: ${m.passive_before}% → ${m.passive_after}%</div>`;
    }
    if (m.complex_before !== undefined && m.complex_after !== undefined && m.complex_after < m.complex_before) {
        metricsHtml += `<div class="fix-metric positive"><i class="fas fa-spell-check"></i> Complex: ${m.complex_before}% → ${m.complex_after}%</div>`;
    }
    if (sd.total_gain > 0) {
        metricsHtml += `<div class="fix-metric positive"><i class="fas fa-chart-line"></i> SEO Gain: +${sd.total_gain} pts</div>`;
    }
    if (sd.word_count_change && sd.word_count_change > 0) {
        metricsHtml += `<div class="fix-metric positive"><i class="fas fa-file-word"></i> +${sd.word_count_change} words</div>`;
    }
    if (!metricsHtml) metricsHtml = `<div class="fix-metric neutral"><i class="fas fa-equals"></i> Minimal measurable change</div>`;
    
    container.innerHTML = `<div class="fix-preview-panel">
        <div class="fix-preview-header">
            <h4><i class="fas fa-magic"></i> AI Fix Preview — ${escHtml(resp.issue_title || 'Issue')}</h4>
            <span class="confidence-badge ${confClass}">${Math.round(conf*100)}% ${confLabel}</span>
        </div>
        <div class="fix-diff-container">
            <div class="fix-diff-col original">
                <div class="fix-diff-label"><i class="fas fa-times-circle"></i> Original</div>
                <div class="fix-diff-text">${escHtml(original.substring(0, 600))}${original.length>600?'...':''}</div>
            </div>
            <div class="fix-diff-col fixed">
                <div class="fix-diff-label"><i class="fas fa-check-circle"></i> AI Optimized</div>
                <div class="fix-diff-text">${escHtml(fixed.substring(0, 600))}${fixed.length>600?'...':''}</div>
            </div>
        </div>
        <div class="fix-metrics-bar">${metricsHtml}</div>
        ${resp.reasoning ? `<div class="fix-reasoning"><i class="fas fa-lightbulb"></i> ${escHtml(resp.reasoning)}</div>` : ''}
        ${changes.length > 0 ? `<div class="fix-changes-list"><h5>Changes Made:</h5><ul>${changes.map(c => '<li>' + escHtml(c) + '</li>').join('')}</ul></div>` : ''}
        <div class="fix-actions-bar">
            <button class="fix-btn fix-btn-success" onclick="seoApplyFix(${issueIdx}, '${blogId}')"><i class="fas fa-check"></i> Apply Fix</button>
            <button class="fix-btn fix-btn-outline" onclick="seoFixPreview(${issueIdx}, '${blogId}')"><i class="fas fa-redo"></i> Regenerate</button>
            <button class="fix-btn fix-btn-outline" onclick="document.getElementById('fix-preview-${issueIdx}').innerHTML=''"><i class="fas fa-times"></i> Dismiss</button>
        </div>
    </div>`;
    
    // Store fix data for apply
    container.dataset.original = original;
    container.dataset.fixed = fixed;
}

async function seoApplyFix(issueIdx, blogId) {
    const container = document.getElementById('fix-preview-' + issueIdx);
    if (!container) return;
    
    const original = container.dataset.original;
    const fixed = container.dataset.fixed;
    const issue = window._seoIssuesCache[issueIdx] || {};
    
    if (!original || !fixed) { alert('No fix data available'); return; }
    
    container.innerHTML = `<div class="fix-progress-overlay" style="position:relative;padding:20px;">
        <div class="spinner"></div>
        <p>Applying fix to blog content...</p>
    </div>`;
    
    const resp = await seoApi('v5_apply_fix', {
        blog_id: parseInt(blogId),
        original_text: original,
        fixed_text: fixed,
        issue: { id: issue.id, title: issue.title, severity: issue.severity }
    });
    
    if (resp.success) {
        container.innerHTML = `<div style="padding:14px 16px;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;margin:8px 0;">
            <i class="fas fa-check-circle" style="color:#22c55e;margin-right:6px;"></i>
            <strong style="color:#166534;">Fix applied successfully!</strong>
            <span style="font-size:0.78rem;color:#16a34a;margin-left:8px;">Content updated in database.</span>
            <button class="fix-btn fix-btn-outline" style="margin-left:12px;font-size:0.72rem;padding:3px 8px;" onclick="seoUndoFix('${blogId}', ${issueIdx})"><i class="fas fa-undo"></i> Undo</button>
        </div>`;
    } else {
        container.innerHTML = `<div style="padding:14px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:8px 0;">
            <i class="fas fa-exclamation-circle" style="color:#dc2626;margin-right:6px;"></i>
            <strong style="color:#991b1b;">Apply failed:</strong> ${escHtml(resp.message || 'Unknown error')}
        </div>`;
    }
}

async function seoUndoFix(blogId, fixIndex) {
    if (!confirm('Undo this fix? The original text will be restored.')) return;
    
    const resp = await seoApi('v5_undo_fix', { blog_id: parseInt(blogId), fix_index: fixIndex });
    
    if (resp.success) {
        alert('Fix undone: ' + (resp.undone_issue || 'Issue restored'));
    } else {
        alert('Undo failed: ' + (resp.message || 'Error'));
    }
}

async function seoBulkFix(blogId, category) {
    const mode = getFixMode();
    const issues = window._seoIssuesCache;
    
    if (!issues || issues.length === 0) { alert('No issues to fix'); return; }
    if (!confirm(`Bulk fix all "${category}" issues using "${mode}" mode?\n\nThis will modify blog content directly.`)) return;
    
    const resultsEl = document.getElementById('bulkFixResults');
    resultsEl.innerHTML = `<div class="fix-progress-overlay" style="position:relative;padding:30px;">
        <div class="spinner"></div>
        <p>Running bulk fix (${category}, ${mode} mode)... This may take a minute.</p>
    </div>`;
    
    const resp = await seoApi('v5_bulk_fix', {
        blog_id: parseInt(blogId),
        issues: issues,
        category: category,
        mode: mode
    });
    
    if (!resp.success) {
        resultsEl.innerHTML = `<div style="padding:12px;color:#dc2626;font-size:0.82rem;"><i class="fas fa-times-circle"></i> ${escHtml(resp.message || 'Bulk fix failed')}</div>`;
        return;
    }
    
    const sb = resp.scores_before || {};
    const sa = resp.scores_after || {};
    const fleschChange = sa.flesch !== undefined && sb.flesch !== undefined ? (sa.flesch - sb.flesch).toFixed(1) : null;
    
    let html = `<div class="bulk-fix-results">
        <div style="padding:10px 14px;background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1px solid #86efac;border-radius:8px;margin-bottom:8px;">
            <strong style="color:#166534;"><i class="fas fa-check-circle"></i> Bulk Fix Complete</strong>
            <span style="margin-left:12px;font-size:0.8rem;color:#16a34a;">${resp.fixed} fixed · ${resp.failed} failed · Total SEO gain: +${resp.total_seo_gain || 0} pts</span>
        </div>
        ${fleschChange !== null ? `<div style="padding:8px 14px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;margin-bottom:8px;font-size:0.8rem;display:flex;gap:16px;flex-wrap:wrap;">
            <span><strong>Flesch:</strong> ${sb.flesch} → ${sa.flesch} <span style="color:${fleschChange>0?'#16a34a':'#dc2626'}">(${fleschChange>0?'+':''}${fleschChange})</span></span>
            <span><strong>Transitions:</strong> ${sb.transition_pct||0}% → ${sa.transition_pct||0}%</span>
            <span><strong>Passive:</strong> ${sb.passive_pct||0}% → ${sa.passive_pct||0}%</span>
            <span><strong>Complex:</strong> ${sb.complex_pct||0}% → ${sa.complex_pct||0}%</span>
            <span><strong>Words:</strong> ${sb.word_count||0} → ${sa.word_count||0}</span>
        </div>` : ''}`;
    
    (resp.results || []).forEach(r => {
        const cls = r.status === 'fixed' ? 'fixed' : r.status === 'failed' ? 'failed' : 'skipped';
        const icon = r.status === 'fixed' ? 'fa-check' : r.status === 'failed' ? 'fa-times' : 'fa-forward';
        html += `<div class="bulk-fix-item ${cls}">
            <i class="fas ${icon}"></i>
            <span style="flex:1;font-weight:600;">${escHtml(r.issue || 'Issue')}</span>
            ${r.gain ? `<span style="color:#16a34a;font-size:0.72rem;">+${r.gain} pts</span>` : ''}
            ${r.reason ? `<span style="color:#94a3b8;font-size:0.72rem;">${escHtml(r.reason)}</span>` : ''}
        </div>`;
    });
    
    html += '</div>';
    resultsEl.innerHTML = html;
}

async function seoViewFixLog(blogId) {
    const panel = document.getElementById('fixLogPanel');
    if (panel.innerHTML.trim()) { panel.innerHTML = ''; return; } // toggle
    
    panel.innerHTML = '<div style="padding:10px;font-size:0.8rem;color:#64748b;"><i class="fas fa-spinner fa-spin"></i> Loading fix log...</div>';
    
    const resp = await seoApi('v5_fix_log', { blog_id: parseInt(blogId) });
    
    if (!resp.success || !resp.logs || resp.logs.length === 0) {
        panel.innerHTML = '<div style="padding:10px;font-size:0.8rem;color:#94a3b8;"><i class="fas fa-info-circle"></i> No fix history found for this blog.</div>';
        return;
    }
    
    let html = `<div class="fix-log-panel">
        <div class="fix-log-header"><i class="fas fa-history"></i> Fix History</div>`;
    
    resp.logs.forEach(log => {
        (log.fixes || []).forEach((fix, fIdx) => {
            html += `<div class="fix-log-entry">
                <div class="log-status"></div>
                <div class="log-issue">${escHtml(fix.issue_title || 'Issue fixed')}</div>
                <div class="log-time">${escHtml(fix.timestamp || log.timestamp || '')}</div>
                <div class="log-undo" onclick="seoUndoFix('${blogId}', ${fIdx})">Undo</div>
            </div>`;
        });
    });
    
    html += '</div>';
    panel.innerHTML = html;
}

// ============================================================
// THIN PARAGRAPHS PREMIUM PANEL RENDERER
// ============================================================
function renderThinParagraphsPanel(issue, blogId) {
    const tp = issue.thin_paragraphs_data || {};
    const all = tp.all || [];
    const count = tp.count || 0;
    const avgWc = tp.avg_words || 0;
    const gain  = tp.score_gain || issue.score_gain || 8;
    const expl  = issue.explanation || {};
    const sevColors = {very_thin:'#ef4444', thin:'#eab308', moderate:'#3b82f6'};
    const depthColor = (d) => d >= 70 ? '#22c55e' : d >= 40 ? '#eab308' : '#ef4444';

    let html = `<div class="da-issue da-open" id="da-issue-tp" style="border:none;background:transparent;box-shadow:none;">
    <div class="da-issue-body" style="display:block;border:none;">
    <div class="da-body-inner" style="padding:0;">`;

    // ── 1. Header Banner with Stats ──
    html += `<div class="tp-header-banner">
        <div class="tp-banner-title">
            <i class="fas fa-exclamation-triangle"></i>
            Thin Paragraphs Detected
            <small>AI Content Depth Analysis</small>
        </div>
        <div class="tp-stats-row">
            <div class="tp-stat">
                <div class="tp-stat-val danger">${count}</div>
                <div class="tp-stat-label">Thin Paragraphs</div>
            </div>
            <div class="tp-stat">
                <div class="tp-stat-val warn">${avgWc}</div>
                <div class="tp-stat-label">Avg Words / Para</div>
            </div>
            <div class="tp-stat">
                <div class="tp-stat-val info">40–80</div>
                <div class="tp-stat-label">Recommended Words</div>
            </div>
            <div class="tp-stat">
                <div class="tp-stat-val success">+${gain}</div>
                <div class="tp-stat-label">Estimated SEO Gain</div>
            </div>
        </div>
    </div>`;

    // ── 2. Score Prediction ──
    html += `<div class="tp-score-predict">
        <div class="tp-score-col">
            <div class="tp-score-num current" id="tp-score-current">—</div>
            <div class="tp-score-lbl">Current Quality</div>
        </div>
        <div class="tp-score-arrow">→</div>
        <div class="tp-score-col">
            <div class="tp-score-num predicted" id="tp-score-predicted">—</div>
            <div class="tp-score-lbl">After Fix</div>
        </div>
        <div>
            <div class="tp-score-gain-badge">+${gain} pts Estimated SEO Gain</div>
            <div style="height:6px;"></div>
            <div class="tp-score-desc">Fixing all ${count} thin paragraphs can significantly improve topical authority, dwell time, and Google's content quality assessment.</div>
        </div>
    </div>`;

    // ── 3. Sticky Nav + Bulk Fix ──
    html += `<div class="tp-sticky-nav">
        <span class="tp-nav-label">Filter:</span>
        <button class="tp-nav-filter active" onclick="tpFilter('all', this)">All (${count})</button>
        <button class="tp-nav-filter" onclick="tpFilter('very_thin', this)">🔴 Very Thin (${all.filter(p=>p.severity_level==='very_thin').length})</button>
        <button class="tp-nav-filter" onclick="tpFilter('thin', this)">🟡 Thin (${all.filter(p=>p.severity_level==='thin').length})</button>
        <button class="tp-nav-filter" onclick="tpFilter('moderate', this)">🔵 Moderate (${all.filter(p=>p.severity_level==='moderate').length})</button>
        <button class="tp-bulk-btn" onclick="tpBulkExpand()"><i class="fas fa-robot"></i> Auto Expand All with AI</button>
    </div>`;

    // ── 4. SEO Impact Explanation ──
    html += `<div class="tp-impact-section">
        <div class="tp-impact-title"><i class="fas fa-chart-line"></i> Why Thin Paragraphs Hurt Your Google Rankings</div>
        <div class="tp-impact-grid">
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Lower Dwell Time</strong> — Readers leave quickly when content is shallow, signalling low value to Google</span></div>
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Weak Topical Authority</strong> — Google measures how thoroughly you cover a topic; thin content scores poorly</span></div>
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Poor Semantic Relevance</strong> — Low word count means fewer semantic keywords, reducing context understanding</span></div>
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Low Engagement Signals</strong> — Bounce rate spikes hurt your domain authority over time</span></div>
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Reduced Content Depth</strong> — Featured snippets favor detailed, well-explained paragraphs (40–80 words)</span></div>
            <div class="tp-impact-item"><i class="fas fa-times-circle"></i><span><strong>Quality Rater Penalty</strong> — Google's QRG specifically flags thin, low-value content as "Lowest Quality"</span></div>
        </div>
        <div class="tp-google-note">
            <strong><i class="fab fa-google" style="margin-right:5px;"></i> How Google Interprets This Issue</strong>
            Google's Helpful Content system evaluates every page for information gain — the unique insight or depth it adds beyond what already exists. Short, underdeveloped paragraphs provide near-zero information gain, which directly suppresses organic rankings. Pages with consistently thin paragraphs are often demoted to page 2+ even with strong backlinks.
        </div>
    </div>`;

    // ── 5. Paragraph Cards ──
    html += `<div class="tp-section-lbl" style="margin-bottom:10px;"><i class="fas fa-list-alt" style="color:#7c1eb4;"></i> Detected Thin Paragraphs — Click to Expand Full AI Analysis</div>`;
    html += `<div class="tp-para-list" id="tp-para-list">`;

    all.forEach((para, pi) => {
        const sev    = para.severity_level || 'moderate';
        const ds     = para.depth_score || 0;
        const dColor = depthColor(ds);
        const wcPct  = Math.min(100, Math.round((para.word_count / 80) * 100));
        const missing = para.missing_elements || [];
        const missingIcons = {
            explanation:'fas fa-question-circle', example:'fas fa-lightbulb',
            statistics:'fas fa-chart-bar', benefits:'fas fa-star',
            user_intent:'fas fa-user', comparison:'fas fa-balance-scale',
            authority:'fas fa-graduation-cap', depth:'fas fa-layer-group'
        };
        const aiRewrite = para.ai_rewrite || '';

        html += `<div class="tp-para-card sev-${sev}" id="tp-card-${pi}" data-sev="${sev}">
            <div class="tp-para-header" onclick="tpToggle(${pi})">
                <div class="tp-para-num">#${para.paragraph_num}</div>
                <div class="tp-para-info">
                    <div class="tp-para-section"><i class="fas fa-heading" style="margin-right:4px;opacity:0.6;"></i>${escHtml(para.section)}</div>
                    <div class="tp-para-preview">${escHtml((para.text||'').substring(0,80))}…</div>
                </div>
                <div class="tp-para-meta">
                    <span class="tp-wc-badge">${para.word_count} words</span>
                    <span class="tp-sev-badge">${escHtml(para.severity_label)}</span>
                    <span style="font-size:0.7rem;color:#94a3b8;">${missing.length} issues</span>
                </div>
                <i class="fas fa-chevron-down tp-expand-icon"></i>
            </div>
            <div class="tp-para-body">

                <!-- Word Count Visual + Depth Meter side by side -->
                <div class="tp-para-body-grid">
                    <div>
                        <div class="tp-section-lbl"><i class="fas fa-ruler-horizontal"></i> Word Count vs Target</div>
                        <div class="tp-wc-visual">
                            <div class="tp-wc-visual-label">
                                <span>${para.word_count} words <strong style="color:${sevColors[sev]||'#94a3b8'};">(${escHtml(para.severity_label)})</strong></span>
                                <span>Target: 50+ words</span>
                            </div>
                            <div class="tp-wc-track">
                                <div class="tp-wc-fill ${sev}" style="width:${wcPct}%;"></div>
                                <div class="tp-wc-target"></div>
                            </div>
                            <div style="font-size:0.7rem;color:#94a3b8;margin-top:4px;">Need ${Math.max(0, 50 - para.word_count)} more words to reach minimum</div>
                        </div>
                    </div>
                    <div>
                        <div class="tp-section-lbl"><i class="fas fa-microscope"></i> Content Depth Score</div>
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                            <div style="font-size:1.8rem;font-weight:900;color:${dColor};">${ds}</div>
                            <div>
                                <div style="font-size:0.72rem;font-weight:700;color:${dColor};">${ds >= 70 ? 'GOOD' : ds >= 40 ? 'WEAK' : 'VERY WEAK'}</div>
                                <div style="font-size:0.68rem;color:#94a3b8;">/ 100 max depth</div>
                            </div>
                        </div>
                        <div class="tp-depth-meter">
                            <div class="tp-depth-track">
                                <div class="tp-depth-fill" style="width:${ds}%;background:${dColor};"></div>
                            </div>
                        </div>
                        <div style="font-size:0.7rem;color:#94a3b8;margin-top:4px;">Semantic richness: ${ds < 40 ? 'Very Low' : ds < 70 ? 'Low' : 'Acceptable'}</div>
                    </div>
                </div>

                <!-- Original paragraph preview -->
                <div class="tp-section-lbl"><i class="fas fa-eye" style="color:#f97316;"></i> Original Content Preview</div>
                <div class="tp-content-preview">
                    <div class="tp-preview-label">📄 Paragraph #${para.paragraph_num} — Section: "${escHtml(para.section)}"</div>
                    <div class="tp-preview-text"><span class="tp-highlight-weak">${escHtml(para.text||'')}</span></div>
                </div>

                <!-- AI Content Analysis: Missing Elements -->
                <div class="tp-section-lbl"><i class="fas fa-robot" style="color:#7c1eb4;"></i> AI Content Depth Analysis — Missing Elements</div>
                ${missing.length > 0 ? `<div class="tp-missing-grid">
                    ${missing.map(m => `<span class="tp-missing-tag ${m.type}"><i class="fas fa-times-circle"></i> ${escHtml(m.label)}</span>`).join('')}
                </div>` : `<div style="background:#f0fdf4;border-radius:8px;padding:8px 12px;font-size:0.8rem;color:#16a34a;margin-bottom:14px;"><i class="fas fa-check-circle"></i> Good depth signals detected — just needs more length</div>`}

                <!-- AI Rewrite Engine -->
                <div class="tp-section-lbl"><i class="fas fa-magic" style="color:#7c1eb4;"></i> AI Expanded Version</div>
                <div class="tp-rewrite-box">
                    <div class="tp-rewrite-label"><i class="fas fa-robot"></i> AI Rewrite Suggestion</div>
                    <div class="tp-rewrite-text">${escHtml(aiRewrite)}</div>
                </div>

                <!-- Smart Fix Buttons -->
                <div class="tp-section-lbl"><i class="fas fa-tools" style="color:#22c55e;"></i> Smart Fix Actions</div>
                <div class="tp-fix-btns">
                    <button class="tp-fix-btn ai"     onclick="tpCopyRewrite(${pi})"><i class="fas fa-robot"></i> Copy AI Rewrite</button>
                    <button class="tp-fix-btn merge"  title="Open editor and merge nearby paragraphs"><i class="fas fa-compress-arrows-alt"></i> Merge Nearby Paras</button>
                    <button class="tp-fix-btn detail" title="Add supporting details to expand content"><i class="fas fa-plus-circle"></i> Add Supporting Details</button>
                    <button class="tp-fix-btn sem"    title="Improve semantic keyword depth"><i class="fas fa-tags"></i> Improve Semantic Depth</button>
                    <button class="tp-fix-btn faq"    title="Convert point into a FAQ for better engagement"><i class="fas fa-question-circle"></i> Add FAQ</button>
                    <button class="tp-fix-btn human"  title="Humanize and make more natural"><i class="fas fa-heart"></i> Humanize Content</button>
                </div>

                <!-- Navigate to content -->
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                    <a href="blog_edit.php?id=${blogId}" class="tp-view-btn" target="_blank">
                        <i class="fas fa-edit"></i> Open in Blog Editor
                    </a>
                    <span style="font-size:0.72rem;color:#94a3b8;">→ Find paragraph #${para.paragraph_num} and expand it using the AI rewrite above</span>
                </div>

            </div>
        </div>`;
    });

    html += `</div>`; // tp-para-list

    // ── 6. Bulk Fix Call to Action ──
    html += `<div style="background:linear-gradient(135deg,#faf5ff,#ede9fe);border:1.5px solid #c4b5fd;border-radius:12px;padding:20px;margin-bottom:20px;text-align:center;">
        <div style="font-size:1rem;font-weight:800;color:#6d28d9;margin-bottom:6px;"><i class="fas fa-robot"></i> Auto Expand All ${count} Thin Paragraphs</div>
        <p style="font-size:0.82rem;color:#7c3aed;margin:0 0 14px;">AI will expand all thin paragraphs naturally — preserving tone, avoiding keyword stuffing, improving readability and semantic SEO.</p>
        <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <a href="blog_edit.php?id=${blogId}" class="tp-view-btn purple" target="_blank"><i class="fas fa-edit"></i> Go to Blog Editor</a>
            <button class="tp-bulk-btn" onclick="tpBulkExpand()"><i class="fas fa-magic"></i> Preview All AI Rewrites</button>
        </div>
    </div>`;

    html += `</div></div></div>`; // close da-body-inner, da-issue-body, da-issue

    return html;
}

function tpToggle(idx) {
    const card = document.getElementById('tp-card-' + idx);
    if (!card) return;
    const isOpen = card.classList.contains('tp-open');
    document.querySelectorAll('.tp-para-card.tp-open').forEach(c => c.classList.remove('tp-open'));
    if (!isOpen) {
        card.classList.add('tp-open');
        setTimeout(() => card.scrollIntoView({behavior: 'smooth', block: 'nearest'}), 50);
    }
}

function tpFilter(sev, btn) {
    document.querySelectorAll('.tp-nav-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.tp-para-card').forEach(card => {
        if (sev === 'all' || card.dataset.sev === sev) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function tpBulkExpand() {
    document.querySelectorAll('.tp-para-card').forEach((card, i) => {
        card.classList.add('tp-open');
    });
}

function tpCopyRewrite(idx) {
    const card = document.getElementById('tp-card-' + idx);
    if (!card) return;
    const rewriteEl = card.querySelector('.tp-rewrite-text');
    if (!rewriteEl) return;
    navigator.clipboard.writeText(rewriteEl.textContent.trim()).then(() => {
        const btn = card.querySelector('.tp-fix-btn.ai');
        if (btn) { const orig = btn.innerHTML; btn.innerHTML = '<i class="fas fa-check"></i> Copied!'; setTimeout(() => btn.innerHTML = orig, 2000); }
    });
}

// Populate live score prediction once overall score is known
function tpPopulateScore(overall, gain) {
    const cur  = document.getElementById('tp-score-current');
    const pred = document.getElementById('tp-score-predicted');
    if (cur)  cur.textContent  = overall + '%';
    if (pred) pred.textContent = Math.min(100, overall + gain) + '%';
}

// AI Semantic Analysis
async function runAiSemantic() {
    const blogId = document.getElementById('analyzerBlogSelect').value;
    if (!blogId) { alert('Select a blog first'); return; }

    const container = document.getElementById('aiSemanticResults');
    container.style.display = 'block';
    container.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Running AI Semantic Analysis...</p></div>';

    // First get blog content
    const blogResp = await seoApi('analyze_blog', {blog_id: parseInt(blogId)});
    if (!blogResp.success) { container.innerHTML = '<p style="color:red;">Failed to load blog</p>'; return; }

    const kw = blogResp.results.blog?.title || '';
    const resp = await seoApi('ai_semantic', {keyword: kw, content: ''});
    if (!resp.success) { container.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message)+'</p></div>'; return; }

    const d = resp.data;
    let html = `<div class="ai-result">
        <h4><i class="fas fa-brain" style="color:var(--seo-purple);margin-right:8px;"></i> AI Semantic Analysis</h4>
        ${d.semantic_score ? `<div style="margin-bottom:12px;"><strong>Semantic Score:</strong> <span class="score-badge" style="background:${d.semantic_score>=70?'rgba(34,197,94,0.1)':'rgba(239,68,68,0.1)'};padding:3px 10px;border-radius:10px;font-weight:700;">${d.semantic_score}/100</span></div>` : ''}
        ${d.missing_entities?.length ? `<div><strong>Missing Entities:</strong><ul>${d.missing_entities.map(e => '<li>'+escHtml(e)+'</li>').join('')}</ul></div>` : ''}
        ${d.lsi_keywords?.length ? `<div><strong>LSI Keywords:</strong><ul>${d.lsi_keywords.map(k => '<li>'+escHtml(k)+'</li>').join('')}</ul></div>` : ''}
        ${d.content_gaps?.length ? `<div><strong>Content Gaps:</strong><ul>${d.content_gaps.map(g => '<li>'+escHtml(g)+'</li>').join('')}</ul></div>` : ''}
        ${d.recommendations?.length ? `<div><strong>Recommendations:</strong><ul>${d.recommendations.map(r => '<li>'+escHtml(r)+'</li>').join('')}</ul></div>` : ''}
    </div>`;
    container.innerHTML = html;
}

// AI EEAT Analysis
async function runAiEeat() {
    const blogId = document.getElementById('analyzerBlogSelect').value;
    if (!blogId) { alert('Select a blog first'); return; }

    const container = document.getElementById('aiEeatResults');
    container.style.display = 'block';
    container.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Running AI E-E-A-T Analysis...</p></div>';

    const resp = await seoApi('ai_eeat', {content: '', category: ''});
    if (!resp.success) { container.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message)+'</p></div>'; return; }

    const d = resp.data;
    let html = `<div class="ai-result">
        <h4><i class="fas fa-shield-alt" style="color:var(--seo-green);margin-right:8px;"></i> AI E-E-A-T Analysis</h4>
        ${d.eeat_score ? `<div style="margin-bottom:12px;"><strong>E-E-A-T Score:</strong> <span class="score-badge" style="background:${d.eeat_score>=70?'rgba(34,197,94,0.1)':'rgba(239,68,68,0.1)'};padding:3px 10px;border-radius:10px;font-weight:700;">${d.eeat_score}/100</span></div>` : ''}
        ${d.experience_suggestions?.length ? `<div><strong>Experience:</strong><ul>${d.experience_suggestions.map(s => '<li>'+escHtml(s)+'</li>').join('')}</ul></div>` : ''}
        ${d.expertise_suggestions?.length ? `<div><strong>Expertise:</strong><ul>${d.expertise_suggestions.map(s => '<li>'+escHtml(s)+'</li>').join('')}</ul></div>` : ''}
        ${d.authority_suggestions?.length ? `<div><strong>Authority:</strong><ul>${d.authority_suggestions.map(s => '<li>'+escHtml(s)+'</li>').join('')}</ul></div>` : ''}
        ${d.trust_suggestions?.length ? `<div><strong>Trust:</strong><ul>${d.trust_suggestions.map(s => '<li>'+escHtml(s)+'</li>').join('')}</ul></div>` : ''}
    </div>`;
    container.innerHTML = html;
}

// ============================================================
// BULK ANALYSIS
// ============================================================
async function runBulkAnalysis() {
    document.getElementById('bulkResults').innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Analyzing all blogs...</p></div>';

    const resp = await seoApi('bulk_analyze');
    if (!resp.success) { document.getElementById('bulkResults').innerHTML = '<p style="color:red;">'+resp.message+'</p>'; return; }

    let html = `<table class="seo-table">
        <thead><tr><th>Blog</th><th>Status</th><th>Words</th><th>Overall</th><th>Basic</th><th>Keyword</th><th>Readability</th><th>Quality</th><th>Actions</th></tr></thead><tbody>`;

    resp.results.forEach(r => {
        const c = scoreColor(r.overall_score);
        html += `<tr>
            <td><strong>${escHtml(r.title)}</strong><br><small style="color:#94a3b8;">/${r.slug}</small></td>
            <td><span class="issue-badge ${r.status==='published'?'':'warning'}">${r.status}</span></td>
            <td>${r.word_count}</td>
            <td><span class="score-badge" style="background:${c==='green'?'rgba(34,197,94,0.1);color:#16a34a':c==='yellow'?'rgba(234,179,8,0.1);color:#ca8a04':'rgba(239,68,68,0.1);color:#dc2626'}">${r.overall_score}%</span></td>
            <td>${r.basic_seo}%</td>
            <td>${r.keyword}%</td>
            <td>${r.readability}%</td>
            <td>${r.content_quality}%</td>
            <td><a href="blog_edit.php?id=${r.id}" class="seo-btn seo-btn-outline seo-btn-sm"><i class="fas fa-edit"></i></a></td>
        </tr>`;
    });

    html += '</tbody></table>';
    document.getElementById('bulkResults').innerHTML = html;
}

// ============================================================
// TECHNICAL SEO
// ============================================================
async function scanBlogs() {
    document.getElementById('blogScanResults').innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    const resp = await seoApi('scan_blogs');
    if (!resp.success) return;
    const r = resp.results;
    let html = `<div style="margin-bottom:10px;"><strong style="color:var(--seo-red);">${r.total_errors}</strong> errors · <strong style="color:var(--seo-yellow);">${r.total_warnings}</strong> warnings · <strong>${r.blogs_with_issues}/${r.total_blogs}</strong> affected</div>`;
    r.items.forEach(item => {
        html += `<div style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <strong style="font-size:0.85rem;">${escHtml(item.title)}</strong>
                <a href="blog_edit.php?id=${item.id}" class="seo-btn seo-btn-outline seo-btn-sm"><i class="fas fa-edit"></i></a>
            </div>
            <div style="margin-top:4px;">${item.issues.map(i => `<span class="issue-badge ${i.type}">${escHtml(i.text)}</span> `).join('')}</div>
        </div>`;
    });
    if (r.items.length === 0) html += '<p style="color:var(--seo-green);"><i class="fas fa-check-circle"></i> All blogs pass technical SEO checks!</p>';
    document.getElementById('blogScanResults').innerHTML = html;
}

async function scanProducts() {
    document.getElementById('productScanResults').innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    const resp = await seoApi('scan_products');
    if (!resp.success) return;
    const r = resp.results;
    let html = `<div style="margin-bottom:10px;"><strong style="color:var(--seo-red);">${r.total_errors}</strong> errors · <strong style="color:var(--seo-yellow);">${r.total_warnings}</strong> warnings · <strong>${r.products_with_issues}/${r.total_products}</strong> affected</div>`;
    r.items.slice(0, 20).forEach(item => {
        html += `<div style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
            <strong style="font-size:0.85rem;">${escHtml(item.title)}</strong>
            <div style="margin-top:4px;">${item.issues.map(i => `<span class="issue-badge ${i.type}">${escHtml(i.text)}</span> `).join('')}</div>
        </div>`;
    });
    if (r.items.length > 20) html += `<p style="font-size:0.8rem;color:#94a3b8;">+ ${r.items.length-20} more</p>`;
    if (r.items.length === 0) html += '<p style="color:var(--seo-green);"><i class="fas fa-check-circle"></i> All products pass SEO checks!</p>';
    document.getElementById('productScanResults').innerHTML = html;
}

async function findOrphans() {
    const c = document.getElementById('orphanResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Scanning link graph for orphan pages...</p></div>';
    try {
        const resp = await seoApi('orphan_pages');
        if (!resp.success) { c.innerHTML = '<p style="color:var(--seo-red);">'+escHtml(resp.message||'Scan failed')+'</p>'; return; }
        const orphans = resp.orphans || [];
        if (orphans.length === 0) {
            c.innerHTML = '<p style="color:var(--seo-green);padding:16px;text-align:center;"><i class="fas fa-check-circle"></i> No orphan pages — all blogs have healthy inbound links!</p>';
            return;
        }
        let html = `<div style="display:flex;gap:16px;margin-bottom:14px;align-items:center;">
            <strong style="color:var(--seo-red);">${orphans.length} orphan page(s)</strong>
            <small style="color:#94a3b8;">Sorted by risk level — click Auto-Fix for AI-powered link suggestions</small>
        </div>`;
        orphans.forEach(o => {
            const riskColors = {critical:'#dc2626',high:'#ef4444',medium:'#eab308',low:'#22c55e'};
            const riskBg = {critical:'#fef2f2',high:'#fef2f2',medium:'#fefce8',low:'#f0fdf4'};
            const risk = o.orphan_risk || 'high';
            html += `<div class="opp-card" style="border-left:4px solid ${riskColors[risk]||'#ef4444'};background:${riskBg[risk]||'#fef2f2'};">
                <div class="opp-info" style="flex:1;">
                    <h5 style="margin-bottom:4px;">${escHtml(o.title)}</h5>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:0.75rem;">
                        <span style="background:${riskColors[risk]}22;color:${riskColors[risk]};padding:2px 8px;border-radius:10px;font-weight:700;text-transform:uppercase;">${risk} risk</span>
                        <span style="color:#94a3b8;"><i class="fas fa-arrow-down"></i> ${o.inbound_links||0} in</span>
                        <span style="color:#94a3b8;"><i class="fas fa-arrow-up"></i> ${o.outbound_links||0} out</span>
                        <span style="color:#94a3b8;"><i class="fas fa-file-word"></i> ${o.word_count||0} words</span>
                        <span style="color:#94a3b8;">Score: ${o.connectivity_score||0}/100</span>
                    </div>
                    ${o.keywords ? `<div style="margin-top:4px;font-size:0.72rem;color:#94a3b8;">Keywords: ${escHtml(o.keywords)}</div>` : ''}
                </div>
                <div style="display:flex;gap:4px;flex-shrink:0;">
                    <button class="seo-btn seo-btn-gold seo-btn-sm" onclick="autoFixOrphan(${o.id},'${escHtml(o.title).replace(/'/g,"\\'")}')"><i class="fas fa-magic"></i> Auto-Fix</button>
                    <a href="blog_edit.php?id=${o.id}" class="seo-btn seo-btn-outline seo-btn-sm"><i class="fas fa-edit"></i> Edit</a>
                </div>
            </div>`;
        });
        
        // === WEAK PAGES (1-2 inbound, 0 outbound) ===
        const weakPages = resp.weak_pages || [];
        if (weakPages.length > 0) {
            html += `<div style="margin-top:20px;display:flex;gap:16px;align-items:center;margin-bottom:14px;">
                <strong style="color:var(--seo-yellow);"><i class="fas fa-battery-quarter" style="margin-right:6px;"></i>${weakPages.length} weak page(s)</strong>
                <small style="color:#94a3b8;">1-2 inbound links, 0 outbound — needs more internal connectivity</small>
            </div>`;
            weakPages.forEach(w => {
                html += `<div class="opp-card" style="border-left:4px solid #eab308;background:#fefce8;">
                    <div class="opp-info" style="flex:1;">
                        <h5 style="margin-bottom:4px;">${escHtml(w.title)}</h5>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;font-size:0.75rem;">
                            <span style="background:#eab30822;color:#eab308;padding:2px 8px;border-radius:10px;font-weight:700;text-transform:uppercase;">WEAK</span>
                            <span style="color:#94a3b8;"><i class="fas fa-arrow-down"></i> ${w.inbound_links||0} in</span>
                            <span style="color:#94a3b8;"><i class="fas fa-arrow-up"></i> ${w.outbound_links||0} out</span>
                            <span style="color:#94a3b8;"><i class="fas fa-file-word"></i> ${w.word_count||0} words</span>
                            <span style="color:#94a3b8;">Score: ${w.connectivity_score||0}/100</span>
                        </div>
                        ${w.keywords ? `<div style="margin-top:4px;font-size:0.72rem;color:#94a3b8;">Keywords: ${escHtml(w.keywords)}</div>` : ''}
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;">
                        <button class="seo-btn seo-btn-gold seo-btn-sm" onclick="autoFixOrphan(${w.id},'${escHtml(w.title).replace(/'/g,"\\'")}')"><i class="fas fa-magic"></i> Auto-Fix</button>
                        <a href="blog_edit.php?id=${w.id}" class="seo-btn seo-btn-outline seo-btn-sm"><i class="fas fa-edit"></i> Edit</a>
                    </div>
                </div>`;
            });
        }
        
        c.innerHTML = html;
    } catch(e) {
        c.innerHTML = '<p style="color:var(--seo-red);">Error: '+escHtml(e.message)+'</p>';
    }
}

async function autoFixOrphan(blogId, title) {
    const panel = document.getElementById('autoFixPanel');
    const results = document.getElementById('autoFixResults');
    document.getElementById('autoFixTitle').textContent = title;
    panel.style.display = 'block';
    panel.scrollIntoView({behavior:'smooth'});
    results.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI analyzing semantic relationships...</p></div>';
    
    try {
        const resp = await seoApi('orphan_autofix', {blog_id: blogId});
        if (!resp.success) { results.innerHTML = '<p style="color:var(--seo-red);">'+escHtml(resp.error||resp.message||'Failed')+'</p>'; return; }
        
        let html = '<div class="seo-grid-2">';
        
        // Outgoing links (orphan should link to these)
        html += '<div><h4 style="font-size:0.9rem;margin-bottom:10px;color:var(--seo-dark);"><i class="fas fa-arrow-up" style="color:var(--seo-blue);margin-right:6px;"></i>Outgoing Links to Add ('+resp.total_outgoing+')</h4>';
        if (resp.outgoing_suggestions?.length) {
            resp.outgoing_suggestions.forEach(s => {
                html += `<div class="opp-card" style="flex-direction:column;align-items:stretch;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div><strong style="font-size:0.83rem;">${escHtml(s.title)}</strong> <span class="issue-badge warning" style="font-size:0.65rem;">${s.type}</span></div>
                        <span style="font-size:0.72rem;color:#94a3b8;">${Math.round(s.score*100)}%</span>
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;margin-top:4px;">Anchor: "<strong>${escHtml(s.anchor)}</strong>" · ${escHtml(s.reason)}</div>
                    <button class="seo-btn seo-btn-gold seo-btn-sm" style="margin-top:6px;align-self:flex-start;" onclick="insertLink(${blogId},'${escHtml(s.anchor).replace(/'/g,"\\'")}','${escHtml(s.url).replace(/'/g,"\\'")}',this)"><i class="fas fa-link"></i> Insert Link</button>
                </div>`;
            });
        } else { html += '<p style="color:#94a3b8;font-size:0.83rem;">No outgoing link suggestions found</p>'; }
        html += '</div>';
        
        // Incoming links (other pages should link to the orphan)
        html += '<div><h4 style="font-size:0.9rem;margin-bottom:10px;color:var(--seo-dark);"><i class="fas fa-arrow-down" style="color:var(--seo-green);margin-right:6px;"></i>Incoming Links Needed ('+resp.total_incoming+')</h4>';
        if (resp.incoming_suggestions?.length) {
            resp.incoming_suggestions.forEach(s => {
                const orphanUrl = '/blog/' + (resp.blog?.slug || '');
                html += `<div class="opp-card" style="flex-direction:column;align-items:stretch;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div><strong style="font-size:0.83rem;">${escHtml(s.source_title)}</strong></div>
                        <span style="font-size:0.72rem;color:#94a3b8;">${Math.round(s.score*100)}%</span>
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;margin-top:4px;">Anchor: "<strong>${escHtml(s.anchor)}</strong>" · ${escHtml(s.reason)}</div>
                    ${s.insert_context ? `<div style="font-size:0.72rem;color:#94a3b8;margin-top:2px;font-style:italic;">..."${escHtml(s.insert_context)}"...</div>` : ''}
                    <button class="seo-btn seo-btn-primary seo-btn-sm" style="margin-top:6px;align-self:flex-start;" onclick="insertLink(${s.source_id},'${escHtml(s.anchor).replace(/'/g,"\\'")}','${escHtml(orphanUrl).replace(/'/g,"\\'")}',this)"><i class="fas fa-link"></i> Insert into "${escHtml(s.source_title).substring(0,25)}..."</button>
                </div>`;
            });
        } else { html += '<p style="color:#94a3b8;font-size:0.83rem;">No incoming link suggestions found</p>'; }
        html += '</div></div>';
        
        results.innerHTML = html;
    } catch(e) {
        results.innerHTML = '<p style="color:var(--seo-red);">Error: '+escHtml(e.message)+'</p>';
    }
}

async function insertLink(blogId, anchor, url, btn) {
    if (!confirm('Insert link "' + anchor + '" → ' + url + ' into blog #' + blogId + '?')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Inserting...';
    try {
        const resp = await seoApi('orphan_insert_link', {blog_id: blogId, anchor: anchor, url: url});
        if (resp.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> Inserted!';
            btn.style.background = 'var(--seo-green)';
            btn.style.color = 'white';
        } else {
            btn.innerHTML = '<i class="fas fa-times"></i> ' + (resp.error||'Failed');
            btn.style.background = 'var(--seo-red)';
            btn.style.color = 'white';
            btn.disabled = false;
        }
    } catch(e) {
        btn.innerHTML = '<i class="fas fa-times"></i> Error';
        btn.disabled = false;
    }
}

async function loadConnectivityGraph() {
    const panel = document.getElementById('graphPanel');
    const c = document.getElementById('graphResults');
    panel.style.display = 'block';
    panel.scrollIntoView({behavior:'smooth'});
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Building connectivity graph...</p></div>';
    
    try {
        const resp = await seoApi('connectivity_graph');
        if (!resp.success) { c.innerHTML = '<p style="color:var(--seo-red);">Failed to load graph</p>'; return; }
        const g = resp.data;
        const statusColors = {orphan_critical:'#dc2626',orphan:'#ef4444',weak:'#eab308',healthy:'#22c55e',pillar:'#3b82f6'};
        const statusLabels = {orphan_critical:'ORPHAN (Critical)',orphan:'ORPHAN',weak:'Weak',healthy:'Healthy',pillar:'Pillar'};
        
        let html = `<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
            <div style="background:#f8fafc;border-radius:8px;padding:10px 16px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;">${g.total_nodes}</div><div style="font-size:0.72rem;color:#94a3b8;">Pages</div></div>
            <div style="background:#f8fafc;border-radius:8px;padding:10px 16px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;">${g.total_edges}</div><div style="font-size:0.72rem;color:#94a3b8;">Links</div></div>
            <div style="background:#f8fafc;border-radius:8px;padding:10px 16px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:var(--seo-red);">${g.nodes.filter(n=>n.status.startsWith('orphan')).length}</div><div style="font-size:0.72rem;color:#94a3b8;">Orphans</div></div>
            <div style="background:#f8fafc;border-radius:8px;padding:10px 16px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:var(--seo-blue);">${g.nodes.filter(n=>n.status==='pillar').length}</div><div style="font-size:0.72rem;color:#94a3b8;">Pillars</div></div>
        </div>`;
        
        // Legend
        html += '<div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">';
        Object.entries(statusColors).forEach(([k,v]) => {
            html += `<span style="font-size:0.72rem;display:flex;align-items:center;gap:4px;"><span style="width:10px;height:10px;border-radius:50%;background:${v};display:inline-block;"></span>${statusLabels[k]||k}</span>`;
        });
        html += '</div>';
        
        // Node list grouped by status
        const groups = {};
        g.nodes.forEach(n => { if (!groups[n.status]) groups[n.status] = []; groups[n.status].push(n); });
        const order = ['orphan_critical','orphan','weak','healthy','pillar'];
        order.forEach(status => {
            const nodes = groups[status];
            if (!nodes?.length) return;
            html += `<div style="margin-bottom:12px;">
                <div style="font-weight:700;font-size:0.82rem;color:${statusColors[status]};margin-bottom:6px;">${statusLabels[status]} (${nodes.length})</div>`;
            nodes.forEach(n => {
                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;border-bottom:1px solid #f1f5f9;font-size:0.8rem;">
                    <div><span style="width:8px;height:8px;border-radius:50%;background:${statusColors[status]};display:inline-block;margin-right:6px;"></span>${escHtml(n.title)}</div>
                    <div style="color:#94a3b8;font-size:0.72rem;"><i class="fas fa-arrow-down"></i> ${n.inbound} in · <i class="fas fa-arrow-up"></i> ${n.outbound} out</div>
                </div>`;
            });
            html += '</div>';
        });
        
        c.innerHTML = html;
    } catch(e) {
        c.innerHTML = '<p style="color:var(--seo-red);">Error: '+escHtml(e.message)+'</p>';
    }
}

// ============================================================
// INTERNAL LINKING (Enterprise Semantic Engine v2)
// ============================================================
async function findLinkOpportunities(forceRefresh = false) {
    const opEl = document.getElementById('linkOpResults');
    const cnEl = document.getElementById('cannibResults');
    const owEl = document.getElementById('orphanWeakResults');
    
    opEl.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Scanning all content for semantic link opportunities...</p></div>';
    cnEl.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    owEl.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';

    let resp;
    try {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 120000);
        const raw = await fetch(API, {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify({action:'link_opportunities', force: forceRefresh}),
            signal: controller.signal
        });
        clearTimeout(timeout);
        resp = await raw.json();
    } catch(e) {
        const errMsg = e.name === 'AbortError' ? 'Request timed out — your site may have too many pages. Try again.' : ('Network error: ' + e.message);
        opEl.innerHTML = `<div style="padding:20px;text-align:center;"><p style="color:var(--seo-red);font-weight:600;"><i class="fas fa-exclamation-circle"></i> ${escHtml(errMsg)}</p><button class="seo-btn seo-btn-primary seo-btn-sm" onclick="findLinkOpportunities()" style="margin-top:10px;"><i class="fas fa-redo"></i> Retry</button></div>`;
        cnEl.innerHTML = '<p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Scan failed</p>';
        owEl.innerHTML = '<p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Scan failed</p>';
        return;
    }

    if (!resp.success) {
        opEl.innerHTML = `<div style="padding:20px;text-align:center;"><p style="color:var(--seo-red);"><i class="fas fa-exclamation-circle"></i> ${escHtml(resp.message||'Analysis failed')}</p><button class="seo-btn seo-btn-primary seo-btn-sm" onclick="findLinkOpportunities()" style="margin-top:10px;"><i class="fas fa-redo"></i> Retry</button></div>`;
        cnEl.innerHTML = '<p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Scan failed</p>';
        owEl.innerHTML = '<p style="color:#94a3b8;font-size:0.83rem;text-align:center;">Scan failed</p>';
        return;
    }

    // === STATS BAR ===
    const st = resp.stats || {};
    const statsBar = document.getElementById('linkStatsBar');
    statsBar.style.display = 'block';
    document.getElementById('linkStatsGrid').innerHTML = `
        <div class="seo-stat-card"><div class="stat-icon" style="background:rgba(59,130,246,0.1);color:var(--seo-blue);"><i class="fas fa-link"></i></div><div class="stat-value">${st.total_opportunities||0}</div><div class="stat-label">Opportunities</div></div>
        <div class="seo-stat-card"><div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--seo-red);"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-value">${st.total_cannibalized||0}</div><div class="stat-label">Cannibalized</div></div>
        <div class="seo-stat-card"><div class="stat-icon" style="background:rgba(139,92,246,0.1);color:#8b5cf6;"><i class="fas fa-ghost"></i></div><div class="stat-value">${st.total_orphans||0}</div><div class="stat-label">Orphan Pages</div></div>
        <div class="seo-stat-card"><div class="stat-icon" style="background:rgba(234,179,8,0.1);color:var(--seo-yellow);"><i class="fas fa-battery-quarter"></i></div><div class="stat-value">${st.total_weak||0}</div><div class="stat-label">Weak Pages</div></div>
    `;

    // === LINK OPPORTUNITIES ===
    const opps = resp.opportunities || [];
    let html = `<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <strong>${opps.length} link opportunities</strong>
        ${resp.generated_at ? `<small style="color:#94a3b8;">Generated: ${escHtml(resp.generated_at)}</small>` : ''}
    </div>`;
    if (opps.length === 0) {
        html = '<p style="color:var(--seo-green);padding:20px;text-align:center;"><i class="fas fa-check-circle"></i> All content is properly interlinked!</p>';
    } else {
        opps.forEach(o => {
            const sc = o.scores || {};
            const totalPct = Math.round((sc.total||0)*100);
            const matchIcons = {title_mention:'quote-left',keyword_overlap:'key',phrase_match:'search',product_mention:'box'};
            const matchColors = {title_mention:'var(--seo-green)',keyword_overlap:'var(--seo-blue)',phrase_match:'var(--seo-gold)',product_mention:'#22c55e'};
            const dupeRisk = sc.duplicate_risk||0;
            
            html += `<div class="opp-card" style="${dupeRisk>0.5?'border-color:var(--seo-yellow);':''}">
                <div class="opp-info" style="flex:1;">
                    <h5><i class="fas fa-${o.source_type==='blog'?'newspaper':'box'}" style="color:var(--seo-blue);margin-right:6px;font-size:0.75rem;"></i>${escHtml(o.source_title)}</h5>
                    <p style="margin:4px 0;">
                        <i class="fas fa-long-arrow-alt-right" style="margin:0 6px;color:var(--seo-gold);"></i>
                        <i class="fas fa-${o.target_type==='blog'?'newspaper':'box'}" style="margin-right:4px;color:${o.target_type==='product'?'#22c55e':'var(--seo-blue)'};font-size:0.75rem;"></i>
                        <strong>${escHtml(o.target_title)}</strong>
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                        <span class="issue-badge warning"><i class="fas fa-${matchIcons[o.match_type]||'link'}" style="margin-right:3px;"></i>${escHtml(o.reason)}</span>
                        ${o.suggested_anchor ? `<span class="issue-badge" style="background:rgba(59,130,246,0.1);color:var(--seo-blue);">Anchor: "${escHtml(o.suggested_anchor)}"</span>` : ''}
                    </div>
                    <div style="display:flex;gap:8px;margin-top:6px;font-size:0.72rem;color:#94a3b8;">
                        <span title="Total Score"><i class="fas fa-star" style="color:var(--seo-gold);"></i> ${totalPct}%</span>
                        <span title="Relevance">Rel: ${Math.round((sc.relevance||0)*100)}%</span>
                        <span title="Authority">Auth: ${Math.round((sc.authority||0)*100)}%</span>
                        <span title="Anchor Quality">Anch: ${Math.round((sc.anchor_quality||0)*100)}%</span>
                        ${dupeRisk > 0.3 ? `<span style="color:var(--seo-red);" title="Duplicate Risk"><i class="fas fa-copy"></i> Dupe: ${Math.round(dupeRisk*100)}%</span>` : ''}
                    </div>
                </div>
                <a href="blog_edit.php?id=${o.source_id}" class="seo-btn seo-btn-outline seo-btn-sm" style="flex-shrink:0;"><i class="fas fa-edit"></i> Fix</a>
            </div>`;
        });
    }
    opEl.innerHTML = html;

    // === KEYWORD CANNIBALIZATION ===
    const cannibs = resp.cannibalization || [];
    if (cannibs.length === 0) {
        cnEl.innerHTML = '<p style="color:var(--seo-green);text-align:center;"><i class="fas fa-check-circle"></i> No keyword cannibalization detected</p>';
    } else {
        let cHtml = `<div style="margin-bottom:8px;"><strong style="color:var(--seo-red);">${cannibs.length}</strong> cannibalized keywords</div>`;
        cannibs.forEach(c => {
            cHtml += `<div style="padding:8px 0;border-bottom:1px solid #f1f5f9;">
                <div style="font-weight:700;font-size:0.88rem;color:var(--seo-dark);margin-bottom:4px;"><i class="fas fa-exclamation-triangle" style="color:var(--seo-red);margin-right:4px;font-size:0.75rem;"></i>"${escHtml(c.keyword)}" <span style="color:#94a3b8;font-weight:400;font-size:0.78rem;">— ${c.count} blogs competing</span></div>
                <div style="display:flex;flex-wrap:wrap;gap:4px;">${c.blogs.map(b => `<a href="blog_edit.php?id=${b.id}" style="font-size:0.75rem;background:#fef2f2;color:var(--seo-red);padding:2px 8px;border-radius:4px;text-decoration:none;">${escHtml(b.title)}</a>`).join('')}</div>
            </div>`;
        });
        cnEl.innerHTML = cHtml;
    }

    // === ORPHAN & WEAK PAGES ===
    const orphans = resp.orphans || [];
    const weak = resp.weak_pages || [];
    let owHtml = '';
    if (orphans.length > 0) {
        owHtml += `<div style="margin-bottom:12px;"><strong style="color:var(--seo-red);">${orphans.length}</strong> orphan pages <small style="color:#94a3b8;">(no inbound links)</small></div>`;
        orphans.forEach(o => {
            owHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                <div><i class="fas fa-ghost" style="color:var(--seo-red);margin-right:6px;font-size:0.75rem;"></i><span style="font-size:0.83rem;font-weight:600;">${escHtml(o.title)}</span> <small style="color:#94a3b8;">${o.word_count} words</small></div>
                <a href="blog_edit.php?id=${o.id}" style="font-size:0.72rem;color:var(--seo-blue);text-decoration:none;"><i class="fas fa-edit"></i> Fix</a>
            </div>`;
        });
    } else {
        owHtml += '<p style="color:var(--seo-green);margin-bottom:8px;"><i class="fas fa-check-circle"></i> No orphan pages</p>';
    }
    if (weak.length > 0) {
        owHtml += `<div style="margin-top:12px;margin-bottom:8px;"><strong style="color:var(--seo-yellow);">${weak.length}</strong> weak pages <small style="color:#94a3b8;">(1-2 inbound links)</small></div>`;
        weak.forEach(w => {
            owHtml += `<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;">
                <div><i class="fas fa-battery-quarter" style="color:var(--seo-yellow);margin-right:6px;font-size:0.75rem;"></i><span style="font-size:0.83rem;">${escHtml(w.title)}</span> <small style="color:#94a3b8;">${w.inbound_links} links · ${w.word_count} words</small></div>
                <a href="blog_edit.php?id=${w.id}" style="font-size:0.72rem;color:var(--seo-blue);text-decoration:none;"><i class="fas fa-edit"></i> Fix</a>
            </div>`;
        });
    }
    owEl.innerHTML = owHtml || '<p style="color:var(--seo-green);"><i class="fas fa-check-circle"></i> All pages have healthy link profiles</p>';
}

// ============================================================
// KEYWORD RESEARCH
// ============================================================
async function runKeywordResearch() {
    const kw = document.getElementById('kwResearchInput').value.trim();
    if (!kw) { alert('Enter a seed keyword'); return; }

    const container = document.getElementById('kwResults');
    container.style.display = 'block';
    container.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI researching keywords for "'+escHtml(kw)+'"...</p></div>';

    const resp = await seoApi('ai_keywords', {keyword: kw});
    if (!resp.success) { container.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message)+'</p></div>'; return; }

    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-key" style="color:var(--seo-green);margin-right:8px;"></i> Keyword Research Results</h4>';

    if (d.primary_keywords?.length) {
        html += '<div style="margin-bottom:16px;"><strong>Primary Keywords:</strong><table class="seo-table" style="margin-top:8px;"><thead><tr><th>Keyword</th><th>Intent</th><th>Difficulty</th><th>Volume</th></tr></thead><tbody>';
        d.primary_keywords.forEach(k => {
            html += `<tr><td>${escHtml(k.keyword||k)}</td><td>${escHtml(k.intent||'-')}</td><td>${escHtml(k.difficulty||'-')}</td><td>${escHtml(k.volume_estimate||'-')}</td></tr>`;
        });
        html += '</tbody></table></div>';
    }

    if (d.long_tail_keywords?.length) html += `<div style="margin-bottom:12px;"><strong>Long-tail Keywords:</strong><ul>${d.long_tail_keywords.map(k => '<li>'+escHtml(k)+'</li>').join('')}</ul></div>`;
    if (d.question_keywords?.length) html += `<div style="margin-bottom:12px;"><strong>Question Keywords:</strong><ul>${d.question_keywords.map(k => '<li>'+escHtml(k)+'</li>').join('')}</ul></div>`;
    if (d.buyer_keywords?.length) html += `<div style="margin-bottom:12px;"><strong>Buyer Intent Keywords:</strong><ul>${d.buyer_keywords.map(k => '<li>'+escHtml(k)+'</li>').join('')}</ul></div>`;
    if (d.content_ideas?.length) {
        html += '<div><strong>Content Ideas:</strong><div style="margin-top:8px;">';
        d.content_ideas.forEach(idea => {
            html += `<div class="opp-card"><div class="opp-info"><h5>${escHtml(idea.title||idea)}</h5><p>${escHtml(idea.keyword||'')} · ${escHtml(idea.type||'')}</p></div></div>`;
        });
        html += '</div></div>';
    }

    html += '</div>';
    container.innerHTML = html;
}

async function runContentBrief() {
    const kw = document.getElementById('kwResearchInput').value.trim();
    if (!kw) { alert('Enter a keyword'); return; }

    const container = document.getElementById('briefResults');
    container.style.display = 'block';
    container.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Generating content brief...</p></div>';

    const resp = await seoApi('ai_brief', {keyword: kw});
    if (!resp.success) { container.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message)+'</p></div>'; return; }

    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-file-alt" style="color:var(--seo-blue);margin-right:8px;"></i> AI Content Brief</h4>';

    if (d.title_suggestions?.length) html += `<div style="margin-bottom:12px;"><strong>Title Suggestions:</strong><ul>${d.title_suggestions.map(t => '<li><strong>'+escHtml(t)+'</strong></li>').join('')}</ul></div>`;
    if (d.meta_description) html += `<div style="margin-bottom:12px;"><strong>Meta Description:</strong><p style="background:#f8fafc;padding:10px;border-radius:8px;font-size:0.85rem;">${escHtml(d.meta_description)}</p></div>`;
    if (d.target_word_count) html += `<div style="margin-bottom:12px;"><strong>Target Word Count:</strong> ${d.target_word_count} words</div>`;
    if (d.search_intent) html += `<div style="margin-bottom:12px;"><strong>Search Intent:</strong> <span class="issue-badge warning">${escHtml(d.search_intent)}</span></div>`;
    if (d.headings?.length) html += `<div style="margin-bottom:12px;"><strong>Recommended Headings:</strong><ul>${d.headings.map(h => '<li>'+escHtml(h)+'</li>').join('')}</ul></div>`;
    if (d.questions_to_answer?.length) html += `<div style="margin-bottom:12px;"><strong>Questions to Answer:</strong><ul>${d.questions_to_answer.map(q => '<li>'+escHtml(q)+'</li>').join('')}</ul></div>`;
    if (d.keywords_to_include?.length) html += `<div style="margin-bottom:12px;"><strong>Keywords to Include:</strong><ul>${d.keywords_to_include.map(k => '<li>'+escHtml(k)+'</li>').join('')}</ul></div>`;
    if (d.schema_recommendations?.length) html += `<div><strong>Schema Recommendations:</strong> ${d.schema_recommendations.map(s => '<span class="issue-badge warning" style="margin-right:4px;">'+escHtml(s)+'</span>').join('')}</div>`;

    html += '</div>';
    container.innerHTML = html;
}

// ============================================================
// TOPIC CLUSTERS
// ============================================================
async function generateCluster() {
    const topic = document.getElementById('clusterTopicInput').value.trim();
    if (!topic) { alert('Enter a pillar topic'); return; }

    const container = document.getElementById('clusterResults');
    container.style.display = 'block';
    container.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Generating topic cluster for "'+escHtml(topic)+'"...</p></div>';

    const resp = await seoApi('ai_cluster', {topic});
    if (!resp.success) { container.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message)+'</p></div>'; return; }

    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-project-diagram" style="color:var(--seo-purple);margin-right:8px;"></i> Topic Cluster Map</h4>';

    if (d.pillar_page) {
        html += `<div style="background:linear-gradient(135deg,#1a3c34,#2d6a4f);color:white;padding:16px 20px;border-radius:10px;margin-bottom:16px;">
            <div style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;opacity:0.7;margin-bottom:4px;">PILLAR PAGE</div>
            <div style="font-size:1.1rem;font-weight:700;">${escHtml(d.pillar_page.title || '')}</div>
            <div style="font-size:0.82rem;opacity:0.8;margin-top:4px;">Target: ${escHtml(d.pillar_page.target_keyword || '')} · ${d.pillar_page.word_count || 3000} words</div>
        </div>`;
    }

    if (d.cluster_articles?.length) {
        html += '<div><strong>Cluster Articles:</strong><div style="margin-top:8px;">';
        d.cluster_articles.forEach((a, i) => {
            html += `<div class="opp-card">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--seo-gold);color:white;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;">${i+1}</div>
                <div class="opp-info">
                    <h5>${escHtml(a.title||'')}</h5>
                    <p>Keyword: ${escHtml(a.target_keyword||'')} · ${a.word_count||1500} words</p>
                </div>
            </div>`;
        });
        html += '</div></div>';
    }

    if (d.internal_link_strategy) html += `<div style="margin-top:16px;"><strong>Link Strategy:</strong><p style="font-size:0.85rem;color:#475569;">${escHtml(d.internal_link_strategy)}</p></div>`;

    html += '</div>';
    container.innerHTML = html;
}

// ============================================================
// CONTENT WORKFLOW
// ============================================================
async function loadChecklist() {
    const blogId = document.getElementById('workflowBlogSelect').value;
    if (!blogId) { alert('Select a blog'); return; }

    document.getElementById('workflowResults').innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';

    const resp = await seoApi('content_workflow', {sub_action: 'get_checklist', blog_id: parseInt(blogId)});
    if (!resp.success) { document.getElementById('workflowResults').innerHTML = '<p style="color:red;">'+escHtml(resp.message)+'</p>'; return; }

    let html = `<div style="text-align:center;margin-bottom:16px;">
        <div class="score-circle ${resp.ready_to_publish ? 'green' : 'yellow'}">
            <span>${resp.completion}%</span>
            <small>${resp.ready_to_publish ? 'READY' : 'INCOMPLETE'}</small>
        </div>
    </div>`;

    resp.checklist.forEach(c => {
        html += `<div class="seo-check">
            <i class="fas fa-${c.done?'check-circle pass':'times-circle'+(c.required?' fail':' warn')}"></i>
            <span>${escHtml(c.item)} ${c.required?'<small style="color:var(--seo-red);">(required)</small>':''}</span>
        </div>`;
    });

    if (resp.ready_to_publish) {
        html += '<div style="margin-top:16px;padding:12px;background:rgba(34,197,94,0.1);border-radius:8px;text-align:center;color:#16a34a;font-weight:600;"><i class="fas fa-check-circle"></i> Ready to publish!</div>';
    }

    document.getElementById('workflowResults').innerHTML = html;
}

// ============================================================
// V3: SEMANTIC INTELLIGENCE
// ============================================================
async function getBlogContent(blogId) {
    const resp = await seoApi('analyze_blog', {blog_id: parseInt(blogId)});
    return resp.success ? resp.results : null;
}

async function runEntityExtract() {
    const blogId = document.getElementById('entityBlogSelect').value;
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('entityResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Extracting entities with AI...</p></div>';
    const blog = await getBlogContent(blogId);
    const resp = await seoApi('v3_entity_extract', {content: blog?.blog?.content || blog?.blog?.title || '', keyword: blog?.blog?.keyword || blog?.blog?.title || ''});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-sitemap" style="color:var(--seo-purple);margin-right:8px;"></i> Extracted Entities</h4>';
    if (d.entities?.length) {
        html += '<table class="seo-table"><thead><tr><th>Entity</th><th>Type</th><th>Salience</th></tr></thead><tbody>';
        d.entities.forEach(e => {
            const sal = (e.salience||0);
            html += `<tr><td><strong>${escHtml(e.name)}</strong></td><td><span class="issue-badge warning">${escHtml(e.type||'concept')}</span></td><td><div style="background:#f1f5f9;border-radius:4px;height:8px;width:100px;"><div style="background:var(--seo-green);height:8px;border-radius:4px;width:${sal*100}%;"></div></div></td></tr>`;
        });
        html += '</tbody></table>';
    }
    if (d.topics?.length) html += `<div style="margin-top:12px;"><strong>Topics:</strong> ${d.topics.map(t=>'<span class="issue-badge warning" style="margin:2px;">'+escHtml(t)+'</span>').join(' ')}</div>`;
    if (d.relationships?.length) {
        html += '<div style="margin-top:12px;"><strong>Relationships:</strong>';
        d.relationships.slice(0,10).forEach(r => {
            html += `<div style="font-size:0.83rem;padding:4px 0;"><strong>${escHtml(r.subject)}</strong> <span style="color:var(--seo-gold);">→ ${escHtml(r.predicate)} →</span> <strong>${escHtml(r.object)}</strong></div>`;
        });
        html += '</div>';
    }
    html += '</div>';
    c.innerHTML = html;
}

async function runKnowledgeGraph() {
    const blogId = document.getElementById('entityBlogSelect').value;
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('entityResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Building knowledge graph...</p></div>';
    const blog = await getBlogContent(blogId);
    const resp = await seoApi('v3_knowledge_graph', {content: blog?.blog?.content || blog?.blog?.title || '', keyword: blog?.blog?.keyword || blog?.blog?.title || ''});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const g = resp.data.graph;
    let html = '<div class="ai-result"><h4><i class="fas fa-project-diagram" style="color:var(--seo-gold);margin-right:8px;"></i> Knowledge Graph</h4>';
    html += `<div style="margin-bottom:10px;"><strong>${g.nodes?.length||0}</strong> nodes · <strong>${g.edges?.length||0}</strong> relationships</div>`;
    if (g.nodes?.length) {
        html += '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">';
        g.nodes.forEach(n => {
            const colors = {person:'#3b82f6',organization:'#8b5cf6',product:'#22c55e',concept:'#eab308',place:'#ef4444',attribute:'#06b6d4',process:'#f97316'};
            html += `<div style="background:${colors[n.type]||'#94a3b8'}22;border:1px solid ${colors[n.type]||'#94a3b8'};border-radius:20px;padding:4px 12px;font-size:0.78rem;font-weight:600;color:${colors[n.type]||'#94a3b8'};">${escHtml(n.label)}</div>`;
        });
        html += '</div>';
    }
    if (g.edges?.length) {
        html += '<div><strong>Connections:</strong>';
        g.edges.forEach(e => {
            html += `<div style="font-size:0.83rem;padding:3px 0;">${escHtml(e.source_name)} <span style="color:var(--seo-gold);font-weight:600;">→ ${escHtml(e.label)} →</span> ${escHtml(e.target_name)}</div>`;
        });
        html += '</div>';
    }
    html += '</div>';
    c.innerHTML = html;
}

async function runDuplicateDetection() {
    const c = document.getElementById('dupeResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Computing semantic embeddings for all blogs...</p></div>';
    const resp = await seoApi('v3_semantic_duplicates');
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const dupes = resp.data;
    let html = `<div style="margin-bottom:10px;"><strong>${dupes.length}</strong> potential duplicates found (${resp.total_compared} blogs compared)</div>`;
    if (dupes.length === 0) { html += '<p style="color:var(--seo-green);"><i class="fas fa-check-circle"></i> No semantic duplicates detected!</p>'; }
    dupes.forEach(d => {
        const pct = Math.round(d.similarity * 100);
        const isNear = d.is_near_duplicate;
        html += `<div class="opp-card" style="border-color:${isNear?'var(--seo-red)':'var(--seo-yellow)'};">
            <div class="opp-info">
                <h5>${escHtml(d.content_a?.title||'')} <span style="color:var(--seo-gold);">↔</span> ${escHtml(d.content_b?.title||'')}</h5>
                <p><span class="issue-badge ${isNear?'error':'warning'}">${pct}% similar${isNear?' — NEAR DUPLICATE':''}</span></p>
            </div>
        </div>`;
    });
    c.innerHTML = html;
}

async function classifyIntent() {
    const kw = document.getElementById('intentKeyword').value.trim();
    if (!kw) { alert('Enter a keyword'); return; }
    const c = document.getElementById('intentResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i></div>';
    const resp = await seoApi('v3_search_intent', {keyword: kw, deep: true});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">Failed</p>'; return; }
    const d = resp.data;
    const b = d.basic;
    const dp = d.deep;
    const intentColors = {informational:'#3b82f6',transactional:'#22c55e',commercial_investigation:'#eab308',navigational:'#8b5cf6'};
    let html = `<div class="ai-result"><h4><i class="fas fa-crosshairs" style="color:var(--seo-blue);margin-right:8px;"></i> Intent: "${escHtml(kw)}"</h4>`;
    html += `<div style="margin-bottom:12px;"><span style="background:${intentColors[b.primary_intent]||'#94a3b8'}22;color:${intentColors[b.primary_intent]||'#94a3b8'};padding:6px 16px;border-radius:20px;font-weight:700;font-size:0.95rem;">${(b.primary_intent||'').replace(/_/g,' ').toUpperCase()}</span> <span style="color:#94a3b8;margin-left:8px;">${Math.round(b.confidence*100)}% confidence</span></div>`;
    if (dp) {
        if (dp.content_format) html += `<div style="margin-bottom:8px;"><strong>Content Format:</strong> ${escHtml(dp.content_format)}</div>`;
        if (dp.user_stage) html += `<div style="margin-bottom:8px;"><strong>Buyer Stage:</strong> ${escHtml(dp.user_stage)}</div>`;
        if (dp.optimal_word_count) html += `<div style="margin-bottom:8px;"><strong>Optimal Word Count:</strong> ${dp.optimal_word_count}</div>`;
        if (dp.ai_overview_likely !== undefined) html += `<div style="margin-bottom:8px;"><strong>AI Overview Likely:</strong> ${dp.ai_overview_likely ? '✅ Yes' : '❌ No'}</div>`;
        if (dp.conversion_potential) html += `<div style="margin-bottom:8px;"><strong>Conversion Potential:</strong> ${Math.round(dp.conversion_potential*100)}%</div>`;
        if (dp.serp_features?.length) html += `<div><strong>Expected SERP Features:</strong> ${dp.serp_features.map(f=>'<span class="issue-badge warning" style="margin:2px;">'+escHtml(f)+'</span>').join(' ')}</div>`;
    }
    html += '</div>';
    c.innerHTML = html;
}

// ============================================================
// V3: TOPICAL AUTHORITY
// ============================================================
async function calcAuthority() {
    const topic = document.getElementById('authorityTopic').value.trim();
    if (!topic) { alert('Enter a topic'); return; }
    const c = document.getElementById('authorityResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Computing topical authority via embeddings...</p></div>';
    const resp = await seoApi('v3_topical_authority', {topic});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const d = resp.data;
    let html = `<div style="text-align:center;margin-bottom:16px;">
        <div class="score-circle ${scoreColor(d.score)}"><span>${d.score}</span><small>AUTHORITY</small></div>
        <div style="font-size:0.85rem;color:#64748b;">${d.coverage_count} articles cover this topic</div>
    </div>`;
    html += `<div class="seo-grid-2" style="margin-bottom:16px;">
        <div style="background:#f8fafc;border-radius:8px;padding:12px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:var(--seo-blue);">${d.coverage_score||0}%</div><div style="font-size:0.78rem;color:#94a3b8;">Coverage</div></div>
        <div style="background:#f8fafc;border-radius:8px;padding:12px;text-align:center;"><div style="font-size:1.4rem;font-weight:800;color:var(--seo-purple);">${d.depth_score||0}%</div><div style="font-size:0.78rem;color:#94a3b8;">Depth</div></div>
    </div>`;
    if (d.coverage?.length) {
        html += '<div><strong>Related Content:</strong>';
        d.coverage.forEach(c => {
            html += `<div class="opp-card"><div class="opp-info"><h5>${escHtml(c.title)}</h5><p>Relevance: ${Math.round(c.relevance*100)}% · ${c.word_count} words · ${c.views||0} views</p></div></div>`;
        });
        html += '</div>';
    }
    c.innerHTML = html;
}

async function findGaps() {
    const topic = document.getElementById('authorityTopic').value.trim();
    if (!topic) { alert('Enter a topic'); return; }
    const c = document.getElementById('authorityResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI analyzing content gaps...</p></div>';
    const resp = await seoApi('v3_content_gaps', {topic});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-search-minus" style="color:var(--seo-red);margin-right:8px;"></i> Content Gaps</h4>';
    if (d.current_authority !== undefined) html += `<div style="margin-bottom:12px;">Current Authority: <strong>${d.current_authority}%</strong> · Existing: <strong>${d.existing_count}</strong> articles</div>`;
    if (d.gaps?.length) {
        d.gaps.forEach(g => {
            const pColors = {high:'var(--seo-red)',medium:'var(--seo-yellow)',low:'var(--seo-green)'};
            html += `<div class="opp-card"><div class="opp-info"><h5>${escHtml(g.title)}</h5><p>Keyword: ${escHtml(g.keyword||'')} · <span class="issue-badge ${g.priority==='high'?'error':'warning'}">${g.priority} priority</span> · ${escHtml(g.type||'')}</p></div></div>`;
        });
    }
    if (d.authority_improvement) html += `<div style="margin-top:12px;"><strong>Improvement Strategy:</strong><p style="font-size:0.85rem;color:#475569;">${escHtml(d.authority_improvement)}</p></div>`;
    html += '</div>';
    c.innerHTML = html;
}

async function discoverOpportunities() {
    const c = document.getElementById('opportunityResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI discovering content opportunities...</p></div>';
    const resp = await seoApi('v3_content_opportunities');
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const d = resp.data;
    
    // Calculate score from priority/traffic/difficulty if AI didn't provide one
    function oppScore(o) {
        if (o.score !== undefined && o.score !== null) return parseInt(o.score);
        let s = 50;
        if (o.priority === 'high') s += 25; else if (o.priority === 'medium') s += 15; else s += 5;
        if (o.estimated_traffic === 'high') s += 15; else if (o.estimated_traffic === 'medium') s += 10; else s += 5;
        if (o.difficulty === 'easy') s += 10; else if (o.difficulty === 'medium') s += 5;
        return Math.min(99, s);
    }
    
    let html = '<div class="ai-result"><h4><i class="fas fa-lightbulb" style="color:var(--seo-gold);margin-right:8px;"></i> Content Opportunities</h4>';
    if (d.opportunities?.length) {
        d.opportunities.forEach((o,i) => {
            const sc = oppScore(o);
            const scCol = sc >= 80 ? '#22c55e' : sc >= 60 ? '#eab308' : '#ef4444';
            const prCol = o.priority==='high' ? '#ef4444' : o.priority==='medium' ? '#eab308' : '#94a3b8';
            const diffCol = o.difficulty==='easy' ? '#22c55e' : o.difficulty==='medium' ? '#eab308' : '#ef4444';
            const trafCol = o.estimated_traffic==='high' ? '#22c55e' : o.estimated_traffic==='medium' ? '#eab308' : '#94a3b8';
            html += `<div class="opp-card" style="align-items:center;">
                <div style="position:relative;width:52px;height:52px;flex-shrink:0;">
                    <svg viewBox="0 0 36 36" style="width:52px;height:52px;transform:rotate(-90deg);">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="${scCol}" stroke-width="3" stroke-dasharray="${sc} ${100-sc}" stroke-linecap="round"/>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.78rem;font-weight:800;color:${scCol};">${sc}%</div>
                </div>
                <div class="opp-info" style="flex:1;min-width:0;">
                    <h5 style="margin-bottom:4px;">${escHtml(o.title)}</h5>
                    <div style="display:flex;flex-wrap:wrap;gap:5px;font-size:0.72rem;margin-bottom:4px;">
                        <span style="background:${prCol}18;color:${prCol};padding:2px 8px;border-radius:10px;font-weight:700;text-transform:uppercase;">${o.priority||'medium'}</span>
                        <span style="background:#f1f5f9;color:#475569;padding:2px 8px;border-radius:10px;">${escHtml(o.type||'')}</span>
                        <span style="color:${trafCol};"><i class="fas fa-chart-line" style="margin-right:2px;"></i>Traffic: ${escHtml(o.estimated_traffic||'')}</span>
                        <span style="color:${diffCol};"><i class="fas fa-dumbbell" style="margin-right:2px;"></i>${escHtml(o.difficulty||'')}</span>
                    </div>
                    <div style="font-size:0.75rem;color:#64748b;"><i class="fas fa-key" style="margin-right:3px;color:#94a3b8;"></i>${escHtml(o.keyword||'')}</div>
                    <small style="color:#94a3b8;display:block;margin-top:3px;">${escHtml(o.reasoning||'')}</small>
                </div>
            </div>`;
        });
    }
    if (d.underserved_topics?.length) html += `<div style="margin-top:14px;padding:10px 14px;background:#fef3c7;border-radius:8px;"><strong style="color:#92400e;"><i class="fas fa-exclamation-triangle" style="margin-right:4px;"></i>Underserved Topics:</strong> ${d.underserved_topics.map(t=>'<span style="background:#fff;padding:2px 8px;border-radius:6px;margin:2px;display:inline-block;font-size:0.78rem;">'+escHtml(t)+'</span>').join(' ')}</div>`;
    if (d.product_content_gaps?.length) html += `<div style="margin-top:8px;padding:10px 14px;background:#fef2f2;border-radius:8px;"><strong style="color:#991b1b;"><i class="fas fa-box-open" style="margin-right:4px;"></i>Product Content Gaps:</strong> ${d.product_content_gaps.map(t=>'<span style="background:#fff;padding:2px 8px;border-radius:6px;margin:2px;display:inline-block;font-size:0.78rem;">'+escHtml(t)+'</span>').join(' ')}</div>`;
    if (d.trending_opportunities?.length) html += `<div style="margin-top:8px;padding:10px 14px;background:#eff6ff;border-radius:8px;"><strong style="color:#1e40af;"><i class="fas fa-fire" style="margin-right:4px;"></i>Trending:</strong> ${d.trending_opportunities.map(t=>'<span style="background:#fff;padding:2px 8px;border-radius:6px;margin:2px;display:inline-block;font-size:0.78rem;">'+escHtml(t)+'</span>').join(' ')}</div>`;
    if (d.seasonal_opportunities?.length) html += `<div style="margin-top:8px;padding:10px 14px;background:#f0fdf4;border-radius:8px;"><strong style="color:#166534;"><i class="fas fa-calendar-alt" style="margin-right:4px;"></i>Seasonal:</strong> ${d.seasonal_opportunities.map(s=>'<span style="background:#fff;padding:2px 8px;border-radius:6px;margin:2px;display:inline-block;font-size:0.78rem;">'+escHtml(s.topic+' ('+s.best_month+')')+'</span>').join(' ')}</div>`;
    html += '</div>';
    c.innerHTML = html;
}

// ============================================================
// V3: AI SEARCH OPTIMIZATION
// ============================================================
async function optimizeAiSearch() {
    const blogId = document.getElementById('aiSearchBlog').value;
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('aiSearchResults');
    c.style.display = 'block';
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Optimizing for AI search engines...</p></div>';
    const resp = await seoApi('v3_ai_search_optimize', {blog_id: blogId});
    if (!resp.success) { c.innerHTML = '<div class="ai-result"><p style="color:red;">'+escHtml(resp.message||'Failed')+'</p></div>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-robot" style="color:var(--seo-purple);margin-right:8px;"></i> AI Search Optimization Results</h4>';
    html += '<div class="seo-grid-2" style="margin-bottom:16px;">';
    [{k:'ai_overview_score',l:'AI Overview',i:'robot'},{k:'featured_snippet_score',l:'Featured Snippet',i:'star'},{k:'voice_search_score',l:'Voice Search',i:'microphone'},{k:'conversational_score',l:'Conversational',i:'comments'}].forEach(s => {
        if (d[s.k] !== undefined) {
            const v = d[s.k]; const col = scoreColor(v);
            html += `<div style="background:#f8fafc;border-radius:8px;padding:12px;text-align:center;"><i class="fas fa-${s.i}" style="color:${col==='green'?'var(--seo-green)':col==='yellow'?'var(--seo-yellow)':'var(--seo-red)'};font-size:1.2rem;"></i><div style="font-size:1.4rem;font-weight:800;margin-top:4px;">${v}%</div><div style="font-size:0.72rem;color:#94a3b8;">${s.l}</div></div>`;
        }
    });
    html += '</div>';
    if (d.ai_overview_fixes?.length) html += `<div style="margin-bottom:10px;"><strong>AI Overview Fixes:</strong><ul>${d.ai_overview_fixes.map(f=>'<li>'+escHtml(f)+'</li>').join('')}</ul></div>`;
    if (d.snippet_optimization?.length) html += `<div style="margin-bottom:10px;"><strong>Snippet Optimization:</strong><ul>${d.snippet_optimization.map(f=>'<li>'+escHtml(f)+'</li>').join('')}</ul></div>`;
    if (d.conversational_improvements?.length) html += `<div><strong>Conversational Search:</strong><ul>${d.conversational_improvements.map(f=>'<li>'+escHtml(f)+'</li>').join('')}</ul></div>`;
    html += '</div>';
    c.innerHTML = html;
}

async function generateSnippets() {
    const blogId = document.getElementById('aiSearchBlog').value;
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('snippetResults');
    c.style.display = 'block';
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Generating optimized snippets...</p></div>';
    c.scrollIntoView({behavior:'smooth', block:'center'});
    try {
        const resp = await seoApi('v3_generate_snippets', {blog_id: blogId});
        if (!resp.success || !resp.data) { c.innerHTML = '<div class="ai-result"><p style="color:red;"><i class="fas fa-times-circle"></i> '+escHtml(resp.message||'Snippet generation failed. Check AI provider settings.')+'</p></div>'; return; }
        const d = resp.data;
        let html = '<div class="ai-result"><h4><i class="fas fa-magic" style="color:var(--seo-gold);margin-right:8px;"></i> AI-Optimized Snippets</h4>';
        let hasContent = false;
        if (d.definition_snippet) { hasContent = true; html += `<div style="margin-bottom:12px;"><strong>Definition Snippet:</strong><div style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid var(--seo-blue);padding:12px;border-radius:6px;margin-top:6px;font-size:0.88rem;">${escHtml(d.definition_snippet)}</div></div>`; }
        if (d.list_snippet?.length) { hasContent = true; html += `<div style="margin-bottom:12px;"><strong>List Snippet:</strong><ol style="margin-top:6px;">${d.list_snippet.map(i=>'<li style="font-size:0.88rem;">'+escHtml(i)+'</li>').join('')}</ol></div>`; }
        if (d.faq_snippets?.length) {
            hasContent = true;
            html += '<div style="margin-bottom:12px;"><strong>FAQ Snippets:</strong>';
            d.faq_snippets.forEach(f => { html += `<div style="background:#f8fafc;padding:10px;border-radius:8px;margin-top:6px;"><strong style="color:var(--seo-dark);">Q: ${escHtml(f.question||f.q||'')}</strong><p style="margin:4px 0 0;font-size:0.85rem;color:#475569;">A: ${escHtml(f.answer||f.a||'')}</p></div>`; });
            html += '</div>';
        }
        if (d.how_to_steps?.length) { hasContent = true; html += `<div style="margin-bottom:12px;"><strong>How-To Steps:</strong><ol style="margin-top:6px;">${d.how_to_steps.map(s=>'<li style="font-size:0.88rem;">'+escHtml(s)+'</li>').join('')}</ol></div>`; }
        if (d.comparison_snippet) { hasContent = true; html += `<div style="margin-bottom:12px;"><strong>Comparison:</strong><div style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid var(--seo-purple);padding:12px;border-radius:6px;margin-top:6px;font-size:0.88rem;">${escHtml(d.comparison_snippet)}</div></div>`; }
        if (d.expert_quote) { hasContent = true; html += `<div style="margin-bottom:12px;"><strong>Expert Quote:</strong><div style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid var(--seo-gold);padding:12px;border-radius:6px;margin-top:6px;font-size:0.88rem;font-style:italic;">"${escHtml(d.expert_quote)}"</div></div>`; }
        if (!hasContent) html += '<p style="color:#94a3b8;">No snippets generated. Try a different blog post.</p>';
        html += '</div>';
        c.innerHTML = html;
    } catch(e) {
        c.innerHTML = '<div class="ai-result"><p style="color:red;"><i class="fas fa-times-circle"></i> Error: '+escHtml(e.message)+'</p></div>';
    }
}

// ============================================================
// V3: RANKING PREDICTIONS
// ============================================================
async function predictRanking() {
    const blogId = document.getElementById('rankBlogSelect').value;
    const kw = document.getElementById('rankKeyword').value.trim();
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('rankResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI predicting ranking probability...</p></div>';
    const resp = await seoApi('v3_ranking_predict', {blog_id: parseInt(blogId), keyword: kw});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-trophy" style="color:var(--seo-gold);margin-right:8px;"></i> Ranking Probability</h4>';
    if (d.ranking_probability) {
        const rp = d.ranking_probability;
        html += '<div class="seo-grid-3" style="margin-bottom:16px;">';
        [{k:'top_3',l:'Top 3'},{k:'top_10',l:'Top 10'},{k:'top_20',l:'Top 20'},{k:'featured_snippet',l:'Featured Snippet'},{k:'ai_overview',l:'AI Overview'}].forEach(s => {
            if (rp[s.k] !== undefined) {
                const pct = Math.round(rp[s.k]*100);
                html += `<div style="background:#f8fafc;border-radius:8px;padding:12px;text-align:center;"><div style="font-size:1.6rem;font-weight:800;color:${pct>=50?'var(--seo-green)':pct>=25?'var(--seo-yellow)':'var(--seo-red)'};">${pct}%</div><div style="font-size:0.72rem;color:#94a3b8;">${s.l}</div></div>`;
            }
        });
        html += '</div>';
    }
    if (d.estimated_monthly_traffic) html += `<div style="margin-bottom:8px;"><strong>Est. Monthly Traffic:</strong> ~${d.estimated_monthly_traffic}</div>`;
    if (d.time_to_rank_months) html += `<div style="margin-bottom:8px;"><strong>Time to Rank:</strong> ~${d.time_to_rank_months} months</div>`;
    if (d.improvement_actions?.length) {
        html += '<div style="margin-top:12px;"><strong>Improvement Actions:</strong>';
        d.improvement_actions.forEach(a => {
            html += `<div class="opp-card"><div class="opp-info"><h5>${escHtml(a.action)}</h5><p>Impact: <span class="issue-badge ${a.impact==='high'?'error':'warning'}">${a.impact}</span> · Effort: ${escHtml(a.effort||'')}</p></div></div>`;
        });
        html += '</div>';
    }
    if (d.risk_factors?.length) html += `<div style="margin-top:10px;"><strong>Risk Factors:</strong><ul>${d.risk_factors.map(r=>'<li style="color:var(--seo-red);">'+escHtml(r)+'</li>').join('')}</ul></div>`;
    html += '</div>';
    c.innerHTML = html;
}

// ============================================================
// V3: SERP INTELLIGENCE
// ============================================================
async function analyzeSERP() {
    const kw = document.getElementById('serpKeyword').value.trim();
    if (!kw) { alert('Enter a keyword'); return; }
    const c = document.getElementById('serpResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Analyzing SERP via DataForSEO...</p></div>';
    const resp = await seoApi('v3_serp_analyze', {keyword: kw});
    if (!resp.success) { c.innerHTML = '<div class="ai-result"><p style="color:red;"><i class="fas fa-times-circle"></i> '+escHtml(resp.message||'SERP analysis failed')+'</p><p style="font-size:0.83rem;color:#64748b;">Go to <strong>API Center</strong> tab → save your DataForSEO Login & Password → click <strong>"Test DataForSEO Connection"</strong> to verify.</p></div>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result"><h4><i class="fas fa-globe" style="color:var(--seo-blue);margin-right:8px;"></i> SERP Analysis: "'+escHtml(d.keyword||kw)+'"</h4>';
    html += `<div style="margin-bottom:12px;"><strong>Total Results:</strong> ${(d.total_results||0).toLocaleString()} · <strong>Difficulty:</strong> <span class="issue-badge ${d.difficulty_estimate==='easy'?'':'error'}">${(d.difficulty_estimate||'').replace(/_/g,' ')}</span></div>`;
    if (d.serp_features?.length) html += `<div style="margin-bottom:12px;"><strong>SERP Features:</strong> ${d.serp_features.map(f=>'<span class="issue-badge warning" style="margin:2px;">'+escHtml(f)+'</span>').join(' ')}</div>`;
    if (d.competitors?.length) {
        html += '<table class="seo-table"><thead><tr><th>#</th><th>Title</th><th>Domain</th></tr></thead><tbody>';
        d.competitors.forEach(c => {
            html += `<tr><td><strong>${c.position}</strong></td><td>${escHtml(c.title)}<br><small style="color:#94a3b8;">${escHtml(c.url||'')}</small></td><td>${escHtml(c.domain)}</td></tr>`;
        });
        html += '</tbody></table>';
    }
    html += '</div>';
    c.innerHTML = html;
}

// ============================================================
// V3: API CENTER
// ============================================================
async function saveApiSettings() {
    const data = {
        embedding_provider: document.getElementById('cfgEmbeddingProvider').value,
        qdrant_url: document.getElementById('cfgQdrantUrl').value,
        dataforseo_login: document.getElementById('cfgDfsLogin').value,
    };
    // Only send secret fields if user typed a new value (non-empty)
    const qKey = document.getElementById('cfgQdrantKey').value.trim();
    const hfKey = document.getElementById('cfgHfKey').value.trim();
    const dfsPass = document.getElementById('cfgDfsPass').value.trim();
    if (qKey) data.qdrant_api_key = qKey;
    if (hfKey) data.hf_api_key = hfKey;
    if (dfsPass) data.dataforseo_password = dfsPass;
    // Never send masked values
    Object.keys(data).forEach(k => {
        if (data[k] && data[k].includes('***')) delete data[k];
    });
    const resp = await seoApi('v3_save_settings', data);
    if (resp.success) {
        alert('Settings saved! (' + (resp.saved || 0) + ' fields updated)');
        loadApiSettings();
    } else {
        alert('Save failed: ' + (resp.message || 'Unknown error'));
    }
}

let _apiSettingsLoaded = false;
async function loadApiSettings() {
    const resp = await seoApi('v3_get_settings');
    if (!resp.success) return;
    const d = resp.data;
    // Set saved values
    if (d.embedding_provider) document.getElementById('cfgEmbeddingProvider').value = d.embedding_provider;
    document.getElementById('cfgQdrantUrl').value = d.qdrant_url || '';
    // For sensitive fields: show placeholder if value exists (masked), leave empty if not set
    const qKey = document.getElementById('cfgQdrantKey');
    const hfKey = document.getElementById('cfgHfKey');
    const dfsPass = document.getElementById('cfgDfsPass');
    qKey.value = '';
    qKey.placeholder = d.qdrant_api_key ? '••••••• (saved, enter new to change)' : 'Optional for local instances';
    hfKey.value = '';
    hfKey.placeholder = d.hf_api_key ? '••••••• (saved, enter new to change)' : 'For HF embedding provider';
    document.getElementById('cfgDfsLogin').value = d.dataforseo_login || '';
    dfsPass.value = '';
    dfsPass.placeholder = d.dataforseo_password ? '••••••• (saved, enter new to change)' : 'DataForSEO API password';
    
    // If no embedding_provider saved, auto-detect from AI chatbot config
    if (!d.embedding_provider) {
        try {
            const aiResp = await seoApi('ai_ping');
            if (aiResp.success && aiResp.data?.provider) {
                document.getElementById('cfgEmbeddingProvider').value = aiResp.data.provider;
                const hint = document.getElementById('cfgProviderHint');
                if (hint) hint.innerHTML = '<i class="fas fa-info-circle"></i> Auto-detected <strong>' + aiResp.data.provider + '</strong> from <a href="chatbot_settings.php" style="color:#8b5cf6;">AI Settings</a>';
            }
        } catch(e) {}
    } else {
        const hint = document.getElementById('cfgProviderHint');
        if (hint) hint.innerHTML = '';
    }
    _apiSettingsLoaded = true;
}

async function loadApiStats() {
    const resp = await seoApi('v3_api_stats');
    if (!resp.success) return;
    const d = resp.data;
    let html = '';
    if (d.tokens?.length) {
        html += '<table class="seo-table"><thead><tr><th>Provider</th><th>Action</th><th>Calls</th><th>Tokens In</th><th>Tokens Out</th><th>Cost</th></tr></thead><tbody>';
        d.tokens.forEach(t => { html += `<tr><td>${escHtml(t.provider)}</td><td>${escHtml(t.action)}</td><td>${t.calls}</td><td>${(t.total_in||0).toLocaleString()}</td><td>${(t.total_out||0).toLocaleString()}</td><td>$${parseFloat(t.total_cost||0).toFixed(4)}</td></tr>`; });
        html += '</tbody></table>';
    } else { html = '<p style="color:#94a3b8;font-size:0.83rem;">No token usage recorded yet</p>'; }
    document.getElementById('tokenStats').innerHTML = html;

    if (d.cache) {
        document.getElementById('cacheStats').innerHTML = `<div style="display:flex;gap:16px;"><div><strong>${d.cache.total_cached}</strong><br><small style="color:#94a3b8;">Total Cached</small></div><div><strong>${d.cache.added_today}</strong><br><small style="color:#94a3b8;">Added Today</small></div><div><strong>${d.cache.total_hits}</strong><br><small style="color:#94a3b8;">Cache Hits</small></div></div>`;
    }
}

async function indexAllContent() {
    const c = document.getElementById('indexResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Indexing all content into vector database...</p></div>';
    const resp = await seoApi('v3_index_content');
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
    c.innerHTML = `<p style="color:var(--seo-green);font-weight:600;"><i class="fas fa-check-circle"></i> Indexed ${resp.indexed}/${resp.total} blogs into vector database</p>`;
}

// ============================================================
// QDRANT TEST — Ping Qdrant vector DB
// ============================================================
async function testQdrant() {
    const c = document.getElementById('qdrantTestResult');
    c.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#7c3aed;"></i> Testing Qdrant connection...';
    try {
        const resp = await seoApi('v4_qdrant_test');
        if (!resp.success) { c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(resp.message||'Failed') + '</span>'; return; }
        const d = resp.data;
        if (d.status === 'ok') {
            c.innerHTML = `<div style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:8px;padding:10px 14px;">
                <div style="font-weight:700;color:#7c3aed;margin-bottom:6px;"><i class="fas fa-check-circle"></i> Qdrant Connected</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:0.78rem;">
                    <div><strong style="color:#64748b;">URL:</strong> ${escHtml(d.url||'')}</div>
                    <div><strong style="color:#64748b;">Latency:</strong> ${d.ping_ms}ms</div>
                    <div><strong style="color:#64748b;">Version:</strong> ${escHtml(d.version||'—')}</div>
                    <div><strong style="color:#64748b;">Collections:</strong> ${d.collections ?? '—'}</div>
                </div>
            </div>`;
        } else if (d.status === 'no_url') {
            c.innerHTML = `<span style="color:#eab308;"><i class="fas fa-exclamation-triangle"></i> ${escHtml(d.error||'No Qdrant URL configured')}</span>`;
        } else {
            c.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ${escHtml(d.error||'Connection failed')} (${d.ping_ms||0}ms)</span>`;
        }
    } catch(e) {
        c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(e.message) + '</span>';
    }
}

// ============================================================
// HUGGINGFACE TEST — Ping HuggingFace Inference API
// ============================================================
async function testHuggingFace() {
    const c = document.getElementById('hfTestResult');
    c.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#ff9900;"></i> Testing HuggingFace connection...';
    try {
        const resp = await seoApi('v4_hf_test');
        if (!resp.success) { c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(resp.message||'Failed') + '</span>'; return; }
        const d = resp.data;
        if (d.status === 'ok') {
            c.innerHTML = `<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:10px 14px;">
                <div style="font-weight:700;color:#ea580c;margin-bottom:6px;"><i class="fas fa-check-circle"></i> HuggingFace Connected</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:0.78rem;">
                    <div><strong style="color:#64748b;">Model:</strong> ${escHtml(d.model||'')}</div>
                    <div><strong style="color:#64748b;">Latency:</strong> ${d.ping_ms}ms</div>
                    <div><strong style="color:#64748b;">Embedding Dim:</strong> ${d.embedding_dim ?? '—'}</div>
                    <div><strong style="color:#64748b;">Status:</strong> <span style="color:#22c55e;font-weight:700;">Active</span></div>
                </div>
            </div>`;
        } else if (d.status === 'no_key') {
            c.innerHTML = `<span style="color:#eab308;"><i class="fas fa-exclamation-triangle"></i> ${escHtml(d.error||'No HuggingFace API key configured')}</span>`;
        } else {
            c.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ${escHtml(d.error||'Connection failed')} (${d.ping_ms||0}ms)</span>`;
        }
    } catch(e) {
        c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(e.message) + '</span>';
    }
}

// ============================================================
// DATAFORSEO TEST — Ping DataForSEO API with saved credentials
// ============================================================
async function testDataForSeo() {
    const c = document.getElementById('dfsTestResult');
    c.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#64748b;"></i> Testing DataForSEO connection...';
    try {
        const resp = await seoApi('dataforseo_test');
        if (!resp.success) { c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(resp.message||'Failed') + '</span>'; return; }
        const d = resp.data;
        if (d.status === 'ok') {
            c.innerHTML = `<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;">
                <div style="font-weight:700;color:#22c55e;margin-bottom:6px;"><i class="fas fa-check-circle"></i> Connected to DataForSEO</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:0.78rem;">
                    <div><strong style="color:#64748b;">Account:</strong> ${escHtml(d.login||d.plan||'')}</div>
                    <div><strong style="color:#64748b;">Latency:</strong> ${d.ping_ms}ms</div>
                    <div><strong style="color:#64748b;">Balance:</strong> <span style="color:#22c55e;font-weight:700;">$${parseFloat(d.money_balance||0).toFixed(2)} ${d.money_currency||'USD'}</span></div>
                    ${d.rate_limit ? `<div><strong style="color:#64748b;">Daily Limit:</strong> ${d.rate_limit.toLocaleString()} tasks</div>` : ''}
                </div>
            </div>`;
        } else if (d.status === 'no_credentials') {
            c.innerHTML = `<span style="color:#eab308;"><i class="fas fa-exclamation-triangle"></i> ${escHtml(d.error)}</span>`;
        } else {
            c.innerHTML = `<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ${escHtml(d.error||'Connection failed')} (${d.ping_ms||0}ms)</span>`;
        }
    } catch(e) {
        c.innerHTML = '<span style="color:#ef4444;"><i class="fas fa-times-circle"></i> ' + escHtml(e.message) + '</span>';
    }
}

// ============================================================
// AI PING — Auto-fetch config from chatbot_settings & test
// ============================================================
async function pingAI() {
    const c = document.getElementById('aiPingResult');
    c.innerHTML = '<div style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin" style="font-size:1.4rem;color:#8b5cf6;"></i><p style="margin-top:8px;color:#94a3b8;font-size:0.82rem;">Fetching AI config & pinging provider...</p></div>';
    try {
        const resp = await seoApi('ai_ping');
        if (!resp.success) { c.innerHTML = '<p style="color:var(--seo-red);padding:12px;">'+escHtml(resp.message||'Failed')+'</p>'; return; }
        const d = resp.data;
        const providerIcons = {gemini:'<i class="fab fa-google" style="color:#4285f4;"></i>',openai:'<i class="fas fa-robot" style="color:#10a37f;"></i>',claude:'<i class="fas fa-brain" style="color:#cc785c;"></i>'};
        const providerNames = {gemini:'Google Gemini',openai:'OpenAI',claude:'Anthropic Claude'};
        const statusColors = {ok:'#22c55e',error:'#ef4444',no_key:'#eab308',disabled:'#94a3b8',unknown:'#94a3b8'};
        const statusIcons = {ok:'check-circle',error:'times-circle',no_key:'exclamation-triangle',disabled:'ban',unknown:'question-circle'};
        const st = d.ping_status;

        let html = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
            <!-- Config Info -->
            <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <div style="width:48px;height:48px;border-radius:12px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:1.4rem;">
                        ${providerIcons[d.provider]||'<i class="fas fa-microchip" style="color:#64748b;"></i>'}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:1rem;color:#1e293b;">${providerNames[d.provider]||d.provider}</div>
                        <div style="font-size:0.78rem;color:#94a3b8;">Auto-detected from <a href="chatbot_settings.php" style="color:#8b5cf6;">Chatbot Settings</a></div>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:0.8rem;">
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">Model</strong><span style="color:#1e293b;font-weight:600;">${escHtml(d.model)}</span></div>
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">API Key</strong><span style="color:#1e293b;font-family:monospace;font-size:0.75rem;">${escHtml(d.masked_key)}</span></div>
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">Temperature</strong><span style="color:#1e293b;font-weight:600;">${d.temperature}</span></div>
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">Max Tokens</strong><span style="color:#1e293b;font-weight:600;">${d.max_tokens}</span></div>
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">AI Enabled</strong><span style="color:${d.ai_enabled?'#22c55e':'#ef4444'};font-weight:700;">${d.ai_enabled?'YES':'NO'}</span></div>
                    <div style="background:#f8fafc;padding:8px 12px;border-radius:8px;"><strong style="color:#64748b;display:block;font-size:0.7rem;text-transform:uppercase;">Timeout</strong><span style="color:#1e293b;font-weight:600;">${d.timeout}s</span></div>
                </div>
            </div>
            <!-- Ping Result -->
            <div style="text-align:center;padding:20px;background:${statusColors[st]}08;border:2px solid ${statusColors[st]}30;border-radius:12px;">
                <i class="fas fa-${statusIcons[st]||'question-circle'}" style="font-size:2.2rem;color:${statusColors[st]};margin-bottom:8px;display:block;"></i>
                <div style="font-size:1.1rem;font-weight:800;color:${statusColors[st]};text-transform:uppercase;margin-bottom:4px;">
                    ${st==='ok'?'CONNECTED':st==='no_key'?'NO KEY':st==='disabled'?'DISABLED':st==='error'?'FAILED':'UNKNOWN'}
                </div>
                ${d.ping_time_ms ? `<div style="font-size:0.78rem;color:#94a3b8;margin-bottom:6px;">Latency: <strong>${d.ping_time_ms}ms</strong></div>` : ''}
                ${st==='ok' && d.ping_response ? `<div style="font-size:0.82rem;color:#475569;background:#fff;padding:6px 12px;border-radius:6px;display:inline-block;margin-top:4px;font-family:monospace;">${escHtml(d.ping_response)}</div>` : ''}
                ${d.ping_error ? `<div style="font-size:0.78rem;color:#ef4444;margin-top:6px;word-break:break-word;">${escHtml(d.ping_error)}</div>` : ''}
            </div>
        </div>`;
        c.innerHTML = html;
    } catch(e) {
        c.innerHTML = '<p style="color:var(--seo-red);padding:12px;">Error: '+escHtml(e.message)+'</p>';
    }
}

// ============================================================
// API ENDPOINT TESTS
// ============================================================
const API_ENDPOINTS = [
    {id:'db_connection', name:'Database', icon:'fas fa-database', group:'Core'},
    {id:'scan_blogs', name:'Blog Scanner', icon:'fas fa-blog', group:'Core'},
    {id:'scan_products', name:'Product Scanner', icon:'fas fa-box', group:'Core'},
    {id:'site_stats', name:'Site Stats', icon:'fas fa-chart-pie', group:'Core'},
    {id:'analyze_blog', name:'Blog Analyzer', icon:'fas fa-search-plus', group:'Analysis'},
    {id:'bulk_analyze', name:'Bulk Analysis', icon:'fas fa-list-check', group:'Analysis'},
    {id:'link_opportunities', name:'Link Opportunities', icon:'fas fa-link', group:'Linking'},
    {id:'orphan_pages', name:'Orphan Detection', icon:'fas fa-unlink', group:'Linking'},
    {id:'orphan_autofix', name:'Orphan Auto-Fix', icon:'fas fa-magic', group:'Linking'},
    {id:'pre_publish_check', name:'Pre-Publish Gate', icon:'fas fa-shield-alt', group:'Linking'},
    {id:'connectivity_graph', name:'Link Graph', icon:'fas fa-project-diagram', group:'Linking'},
    {id:'ai_semantic', name:'AI Semantic', icon:'fas fa-atom', group:'AI'},
    {id:'v3_entity_extract', name:'Entity Extract', icon:'fas fa-sitemap', group:'AI'},
    {id:'v3_knowledge_graph', name:'Knowledge Graph', icon:'fas fa-brain', group:'AI'},
    {id:'v3_search_intent', name:'Search Intent', icon:'fas fa-bullseye', group:'AI'},
    {id:'v3_semantic_links', name:'Semantic Links', icon:'fas fa-bezier-curve', group:'AI'},
    {id:'v3_semantic_duplicates', name:'Duplicate Detect', icon:'fas fa-clone', group:'AI'},
    {id:'v3_topical_authority', name:'Topical Authority', icon:'fas fa-crown', group:'AI'},
    {id:'v3_content_gaps', name:'Content Gaps', icon:'fas fa-puzzle-piece', group:'AI'},
    {id:'v3_content_opportunities', name:'Opportunities', icon:'fas fa-lightbulb', group:'AI'},
    {id:'v3_ai_search_optimize', name:'AI Search Optimize', icon:'fas fa-robot', group:'AI'},
    {id:'v3_generate_snippets', name:'Snippet Generator', icon:'fas fa-code', group:'AI'},
    {id:'v3_ranking_predict', name:'Ranking Predict', icon:'fas fa-trophy', group:'AI'},
    {id:'v3_serp_analyze', name:'SERP Analysis', icon:'fas fa-globe', group:'Data'},
    {id:'v3_keyword_data', name:'Keyword Data', icon:'fas fa-key', group:'Data'},
    {id:'v3_api_stats', name:'API Stats', icon:'fas fa-chart-bar', group:'Data'},
];

function initApiTestGrid() {
    const grid = document.getElementById('apiTestGrid');
    if (!grid) return;
    const groupColors = {Core:'#3b82f6',Analysis:'#22c55e',Linking:'#8b5cf6',AI:'#eab308',Data:'#64748b'};
    let html = '';
    API_ENDPOINTS.forEach(ep => {
        html += `<div id="apitest-${ep.id}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;transition:all 0.2s;">
            <i class="${ep.icon}" style="color:${groupColors[ep.group]||'#94a3b8'};font-size:0.9rem;width:20px;text-align:center;flex-shrink:0;"></i>
            <div style="flex:1;min-width:0;">
                <div style="font-size:0.8rem;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${ep.name}</div>
                <div class="apitest-detail" style="font-size:0.7rem;color:#94a3b8;">${ep.group}</div>
            </div>
            <div class="apitest-status" style="flex-shrink:0;">
                <button class="seo-btn seo-btn-outline" style="padding:3px 10px;font-size:0.7rem;border-radius:5px;" onclick="runSingleApiTest('${ep.id}')"><i class="fas fa-play" style="font-size:0.6rem;"></i> Test</button>
            </div>
        </div>`;
    });
    grid.innerHTML = html;
}
initApiTestGrid();

async function runSingleApiTest(endpoint) {
    const card = document.getElementById('apitest-' + endpoint);
    if (!card) return;
    const statusEl = card.querySelector('.apitest-status');
    const detailEl = card.querySelector('.apitest-detail');
    statusEl.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#94a3b8;font-size:0.8rem;"></i>';
    card.style.borderColor = '#e2e8f0';
    
    try {
        const resp = await seoApi('api_test', {endpoint});
        if (!resp.success) {
            statusEl.innerHTML = '<span style="color:#ef4444;font-size:0.75rem;font-weight:700;">FAIL</span>';
            detailEl.textContent = resp.message || 'Error';
            card.style.borderColor = '#fecaca';
            return {status:'error'};
        }
        const d = resp.data;
        const colors = {ok:'#22c55e',warning:'#eab308',error:'#ef4444',skip:'#94a3b8'};
        const icons = {ok:'check-circle',warning:'exclamation-triangle',error:'times-circle',skip:'forward'};
        const labels = {ok:'OK',warning:'WARN',error:'FAIL',skip:'SKIP'};
        const st = d.status;
        statusEl.innerHTML = `<span style="display:flex;align-items:center;gap:4px;"><i class="fas fa-${icons[st]||'question'}" style="color:${colors[st]||'#94a3b8'};font-size:0.75rem;"></i><span style="color:${colors[st]||'#94a3b8'};font-size:0.72rem;font-weight:700;">${labels[st]||st}</span><span style="color:#cbd5e1;font-size:0.68rem;">${d.time_ms}ms</span></span>`;
        detailEl.textContent = d.details || '';
        detailEl.style.color = st==='error' ? '#ef4444' : '#94a3b8';
        card.style.borderColor = st==='ok' ? '#bbf7d0' : st==='warning' ? '#fef08a' : st==='error' ? '#fecaca' : '#e2e8f0';
        return {status:st};
    } catch(e) {
        statusEl.innerHTML = '<span style="color:#ef4444;font-size:0.72rem;font-weight:700;">ERR</span>';
        detailEl.textContent = e.message;
        card.style.borderColor = '#fecaca';
        return {status:'error'};
    }
}

async function runAllApiTests() {
    const summary = document.getElementById('apiTestSummary');
    summary.style.display = 'block';
    summary.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i> Running all API tests...';
    
    let ok=0, warn=0, fail=0, skip=0;
    const total = API_ENDPOINTS.length;
    
    for (let i=0; i<total; i++) {
        const ep = API_ENDPOINTS[i];
        summary.innerHTML = `<i class="fas fa-spinner fa-spin" style="margin-right:6px;color:#3b82f6;"></i> Testing <strong>${ep.name}</strong> (${i+1}/${total})...`;
        const result = await runSingleApiTest(ep.id);
        if (result.status === 'ok') ok++;
        else if (result.status === 'warning') warn++;
        else if (result.status === 'skip') skip++;
        else fail++;
    }
    
    summary.innerHTML = `<div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <strong style="color:#1e293b;">Test Results:</strong>
        <span style="color:#22c55e;font-weight:700;"><i class="fas fa-check-circle"></i> ${ok} passed</span>
        ${warn ? `<span style="color:#eab308;font-weight:700;"><i class="fas fa-exclamation-triangle"></i> ${warn} warnings</span>` : ''}
        ${fail ? `<span style="color:#ef4444;font-weight:700;"><i class="fas fa-times-circle"></i> ${fail} failed</span>` : ''}
        ${skip ? `<span style="color:#94a3b8;font-weight:700;"><i class="fas fa-forward"></i> ${skip} skipped</span>` : ''}
        <span style="color:#94a3b8;margin-left:auto;font-size:0.78rem;">${total} endpoints tested</span>
    </div>`;
}

// ============================================================
// V4: WHY NOT RANKING
// ============================================================
async function diagnoseRanking() {
    const blogId = document.getElementById('wnrBlog').value;
    if (!blogId) { alert('Select a blog'); return; }
    const c = document.getElementById('wnrResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Analyzing ranking blockers...</p></div>';
    const resp = await seoApi('v4_why_not_ranking', {blog_id: blogId});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message)+'</p>'; return; }
    const d = resp.data;
    const sevColors = {critical:'#ef4444',high:'#f97316',medium:'#eab308',low:'#22c55e'};
    const sevIcons = {critical:'skull-crossbones',high:'exclamation-triangle',medium:'exclamation-circle',low:'info-circle'};
    let html = `<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
        <div style="background:#fef2f2;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:800;color:#ef4444;">${d.critical_count}</div><div style="font-size:0.75rem;color:#b91c1c;">Critical</div></div>
        <div style="background:#fff7ed;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:800;color:#f97316;">${d.high_count}</div><div style="font-size:0.75rem;color:#c2410c;">High</div></div>
        <div style="background:#fefce8;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:800;color:#eab308;">${d.medium_count}</div><div style="font-size:0.75rem;color:#a16207;">Medium</div></div>
        <div style="background:#f0fdf4;border-radius:10px;padding:14px;text-align:center;"><div style="font-size:1.6rem;font-weight:800;color:#22c55e;">${d.overall_score}/100</div><div style="font-size:0.75rem;color:#15803d;">SEO Score</div></div>
    </div>`;
    if (d.ranking_probability) {
        html += `<div style="background:linear-gradient(135deg,#1e293b,#334155);border-radius:12px;padding:16px;margin-bottom:16px;color:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <strong><i class="fas fa-chart-line" style="margin-right:6px;"></i> Ranking Probability</strong>
                <span style="background:${d.ranking_probability.difficulty==='Achievable'?'#22c55e':d.ranking_probability.difficulty==='Moderate'?'#eab308':'#ef4444'};padding:2px 10px;border-radius:20px;font-size:0.75rem;font-weight:700;">${escHtml(d.ranking_probability.difficulty)}</span>
            </div>
            <div style="display:flex;gap:20px;font-size:0.85rem;">
                <div>Top 10: <strong>${escHtml(d.ranking_probability.top_10)}</strong></div>
                <div>Top 3: <strong>${escHtml(d.ranking_probability.top_3)}</strong></div>
            </div>
        </div>`;
    }
    html += `<div style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:0.85rem;color:#475569;"><i class="fas fa-lightbulb" style="color:#eab308;margin-right:6px;"></i> ${escHtml(d.summary)}</div>`;
    if (d.blockers && d.blockers.length) {
        html += '<div style="display:flex;flex-direction:column;gap:10px;">';
        d.blockers.forEach((b, i) => {
            html += `<div style="border:1px solid ${sevColors[b.severity]}30;border-left:4px solid ${sevColors[b.severity]};border-radius:8px;padding:12px 16px;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:6px;">
                    <div><i class="fas fa-${sevIcons[b.severity]}" style="color:${sevColors[b.severity]};margin-right:6px;"></i><strong style="font-size:0.85rem;">${escHtml(b.issue)}</strong></div>
                    <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;">
                        <span style="background:${sevColors[b.severity]}15;color:${sevColors[b.severity]};padding:2px 8px;border-radius:12px;font-size:0.7rem;font-weight:700;text-transform:uppercase;">${b.severity}</span>
                        <span style="font-size:0.7rem;color:#94a3b8;">Impact: ${b.ranking_impact}%</span>
                    </div>
                </div>
                <div style="font-size:0.8rem;color:#64748b;margin-bottom:4px;"><strong>Category:</strong> ${escHtml(b.category)}</div>
                <div style="font-size:0.8rem;color:#475569;">${escHtml(b.why_it_matters)}</div>
            </div>`;
        });
        html += '</div>';
    }
    c.innerHTML = html;
}

// ============================================================
// V4: PRODUCT DEEP ANALYZER
// ============================================================
async function analyzeProduct() {
    const pid = document.getElementById('prodAnalyzeSel').value;
    if (!pid) { alert('Select a product'); return; }
    const c = document.getElementById('prodAnalyzeResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Analyzing product SEO...</p></div>';
    const resp = await seoApi('v4_product_analyze', {product_id: pid});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message)+'</p>'; return; }
    const d = resp.data;
    const scoreColor = d.product_score >= 70 ? '#22c55e' : d.product_score >= 40 ? '#eab308' : '#ef4444';
    let html = `<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;">
        <div style="background:${scoreColor}10;border:2px solid ${scoreColor}30;border-radius:12px;padding:16px 24px;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:${scoreColor};">${d.product_score}</div>
            <div style="font-size:0.75rem;color:#64748b;">Product SEO Score</div>
        </div>`;
    if (d.ctr) {
        const ctrColor = d.ctr.score >= 60 ? '#22c55e' : d.ctr.score >= 40 ? '#eab308' : '#ef4444';
        html += `<div style="background:${ctrColor}10;border:2px solid ${ctrColor}30;border-radius:12px;padding:16px 24px;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:${ctrColor};">${d.ctr.score}</div>
            <div style="font-size:0.75rem;color:#64748b;">CTR Score</div>
            <div style="font-size:0.7rem;color:#94a3b8;">${escHtml(d.ctr.estimated_ctr)}</div>
        </div>`;
    }
    html += `<div style="background:#f8fafc;border-radius:12px;padding:16px 24px;text-align:center;">
        <div style="font-size:2rem;font-weight:800;color:#3b82f6;">${d.word_count}</div>
        <div style="font-size:0.75rem;color:#64748b;">Words</div>
    </div></div>`;
    // Group checks by category
    if (d.categories) {
        for (const [cat, checks] of Object.entries(d.categories)) {
            html += `<div style="margin-bottom:12px;"><h4 style="font-size:0.85rem;color:#1e293b;margin-bottom:6px;"><i class="fas fa-folder" style="color:var(--seo-blue);margin-right:4px;"></i> ${escHtml(cat)}</h4>`;
            checks.forEach(ch => {
                const icon = ch.status==='pass'?'check-circle':ch.status==='warn'?'exclamation-circle':'times-circle';
                const color = ch.status==='pass'?'#22c55e':ch.status==='warn'?'#eab308':'#ef4444';
                html += `<div style="padding:4px 0;font-size:0.82rem;"><i class="fas fa-${icon}" style="color:${color};margin-right:6px;"></i>${escHtml(ch.text)}</div>`;
            });
            html += '</div>';
        }
    }
    c.innerHTML = html;
}

// ============================================================
// V4: CORE WEB VITALS / PAGESPEED
// ============================================================
async function runPageSpeed() {
    const url = document.getElementById('psUrl').value.trim();
    if (!url) { alert('Enter a URL'); return; }
    const strategy = document.getElementById('psStrategy').value;
    const c = document.getElementById('psResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Running PageSpeed analysis (may take 15-30s)...</p></div>';
    const resp = await seoApi('v4_pagespeed', {url, strategy});
    if (!resp.success) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message)+'</p>'; return; }
    const d = resp.data;
    const psColor = d.performance_score >= 90 ? '#22c55e' : d.performance_score >= 50 ? '#eab308' : '#ef4444';
    let html = `<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap;align-items:center;">
        <div style="width:100px;height:100px;border-radius:50%;border:6px solid ${psColor};display:flex;align-items:center;justify-content:center;flex-direction:column;">
            <div style="font-size:1.8rem;font-weight:800;color:${psColor};">${d.performance_score}</div>
            <div style="font-size:0.6rem;color:#94a3b8;text-transform:uppercase;">${escHtml(d.strategy)}</div>
        </div>
        <div style="flex:1;">
            <h4 style="margin-bottom:8px;color:#1e293b;">Core Web Vitals</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;">`;
    if (d.checks) {
        d.checks.forEach(ch => {
            const color = ch.status==='pass'?'#22c55e':ch.status==='warn'?'#eab308':'#ef4444';
            html += `<div style="background:${color}10;border:1px solid ${color}30;border-radius:8px;padding:10px;text-align:center;">
                <div style="font-size:0.7rem;color:#64748b;font-weight:600;">${escHtml(ch.metric)}</div>
                <div style="font-size:1.1rem;font-weight:700;color:${color};">${escHtml(ch.text.split(':')[1]||'')}</div>
            </div>`;
        });
    }
    html += '</div></div></div>';
    if (d.opportunities && d.opportunities.length) {
        html += '<h4 style="margin:12px 0 8px;color:#1e293b;font-size:0.88rem;"><i class="fas fa-lightbulb" style="color:#eab308;margin-right:4px;"></i> Optimization Opportunities</h4>';
        d.opportunities.forEach(op => {
            html += `<div style="padding:6px 0;font-size:0.82rem;border-bottom:1px solid #f1f5f9;">
                <i class="fas fa-arrow-right" style="color:#3b82f6;margin-right:6px;"></i><strong>${escHtml(op.title)}</strong>
                <span style="color:#94a3b8;font-size:0.75rem;margin-left:6px;">${escHtml(op.savings)}</span>
            </div>`;
        });
    }
    c.innerHTML = html;
}

// ============================================================
// V4: AI ONE-CLICK FIX ENGINE
// ============================================================
async function runAiFix() {
    const fixType = document.getElementById('aiFixType').value;
    const title = document.getElementById('aiFixTitle').value;
    const keyword = document.getElementById('aiFixKeyword').value;
    if (!title && !keyword) { alert('Enter a title or keyword'); return; }
    const c = document.getElementById('aiFixResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>Generating AI fix...</p></div>';
    const resp = await seoApi('v4_ai_fix', {fix_type: fixType, context: {title, keyword, issue: fixType}});
    if (!resp.success || !resp.data) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'AI fix failed')+'</p>'; return; }
    const d = resp.data;
    let html = '<div class="ai-result">';
    for (const [key, val] of Object.entries(d)) {
        if (Array.isArray(val)) {
            html += `<div style="margin-bottom:10px;"><strong style="text-transform:capitalize;">${escHtml(key.replace(/_/g,' '))}:</strong><ul style="margin:4px 0 0 16px;">`;
            val.forEach(item => {
                if (typeof item === 'object') html += '<li style="font-size:0.83rem;margin-bottom:4px;">' + Object.entries(item).map(([k,v])=>`<strong>${escHtml(k)}:</strong> ${escHtml(String(v))}`).join(' | ') + '</li>';
                else html += `<li style="font-size:0.83rem;">${escHtml(String(item))}</li>`;
            });
            html += '</ul></div>';
        } else if (typeof val === 'string' && val.length > 0) {
            html += `<div style="margin-bottom:8px;"><strong style="text-transform:capitalize;">${escHtml(key.replace(/_/g,' '))}:</strong> <span style="color:#475569;font-size:0.85rem;">${escHtml(val)}</span></div>`;
        }
    }
    html += '</div>';
    c.innerHTML = html;
}

// ============================================================
// V4: SCHEMA JSON-LD GENERATOR (Enhanced)
// ============================================================
let _lastGeneratedSchema = null;
let _lastSchemaContentId = '';

async function autoDetectSchemaType() {
    const sel = document.getElementById('schemaBlog').value;
    const typeEl = document.getElementById('schemaType');
    const c = document.getElementById('schemaResults');
    document.getElementById('btnValidateSchema').style.display = 'none';
    document.getElementById('btnApplySchema').style.display = 'none';
    _lastGeneratedSchema = null;
    if (!sel) { c.innerHTML = ''; return; }
    if (sel.startsWith('prod_')) { typeEl.value = 'product'; }
    else if (sel.startsWith('blog_')) { typeEl.value = 'article'; }
    else { typeEl.value = 'auto'; }
    // Check if schema already applied
    c.innerHTML = '<div style="padding:8px;color:#64748b;font-size:0.82rem;"><i class="fas fa-spinner fa-spin"></i> Checking existing schema...</div>';
    try {
        const resp = await seoApi('v4_check_schema', {content_id: sel});
        if (resp.success && resp.data && resp.data.has_schema) {
            const d = resp.data;
            let html = '<div style="padding:12px 16px;border-radius:10px;background:#fffbeb;border:1px solid #fbbf24;margin-bottom:4px;">';
            html += '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><i class="fas fa-exclamation-triangle" style="color:#d97706;font-size:1.1rem;"></i><strong style="color:#92400e;">Schema Already Applied</strong>';
            html += '<span class="issue-badge" style="background:#fef3c7;color:#92400e;font-size:0.72rem;">'+escHtml(d.schema_type)+'</span></div>';
            html += '<p style="font-size:0.82rem;color:#78716c;margin:0 0 10px;">This content already has a custom AI-generated schema injected into its page. Generating a new one will allow you to replace it.</p>';
            html += '<details style="font-size:0.78rem;"><summary style="cursor:pointer;color:#92400e;font-weight:600;">View Current Schema</summary>';
            html += '<pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:8px;overflow-x:auto;font-size:0.75rem;max-height:250px;margin-top:8px;"><code>'+escHtml(JSON.stringify(d.schema,null,2))+'</code></pre></details>';
            html += '</div>';
            c.innerHTML = html;
        } else {
            c.innerHTML = '';
        }
    } catch(e) { c.innerHTML = ''; }
}

async function generateSchema() {
    const schemaType = document.getElementById('schemaType').value;
    const sel = document.getElementById('schemaBlog').value;
    if (!sel && !['organization','collectionpage'].includes(schemaType)) { alert('Select content'); return; }
    const c = document.getElementById('schemaResults');
    c.innerHTML = '<div class="seo-loading"><i class="fas fa-spinner fa-spin"></i><p>AI generating schema from real data...</p></div>';
    document.getElementById('btnValidateSchema').style.display = 'none';
    document.getElementById('btnApplySchema').style.display = 'none';
    _lastGeneratedSchema = null;
    _lastSchemaContentId = sel;

    const resp = await seoApi('v4_generate_schema', {schema_type: schemaType, content_id: sel});
    if (!resp.success || !resp.data) { c.innerHTML = '<p style="color:red;">'+escHtml(resp.message||'Schema generation failed')+'</p>'; return; }
    const d = resp.data;
    _lastGeneratedSchema = d.schema || null;
    _lastSchemaContentId = d.content_id || sel;

    let html = '<div class="ai-result">';
    if (d.detected_type) html += `<div style="margin-bottom:8px;"><span class="issue-badge" style="background:#dbeafe;color:#1d4ed8;">Detected: ${escHtml(d.detected_type.toUpperCase())}</span></div>`;
    html += '<h4><i class="fas fa-code" style="color:var(--seo-gold);margin-right:6px;"></i> Generated JSON-LD Schema</h4>';
    if (d.schema) {
        const jsonStr = JSON.stringify(d.schema, null, 2);
        html += `<div style="position:relative;"><pre style="background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;overflow-x:auto;font-size:0.78rem;max-height:400px;"><code>${escHtml(jsonStr)}</code></pre>
        <button onclick="navigator.clipboard.writeText(this.parentElement.querySelector('code').textContent);this.innerHTML='<i class=\\'fas fa-check\\'></i> Copied!';" style="position:absolute;top:8px;right:8px;background:#3b82f6;color:#fff;border:none;padding:4px 10px;border-radius:6px;font-size:0.72rem;cursor:pointer;"><i class="fas fa-copy"></i> Copy</button></div>`;
        document.getElementById('btnValidateSchema').style.display = '';
        if (sel) document.getElementById('btnApplySchema').style.display = '';
    }
    if (d.additional_schemas && d.additional_schemas.length) {
        html += '<h4 style="margin-top:14px;">Additional Schemas</h4>';
        d.additional_schemas.forEach(s => {
            html += `<pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:8px;overflow-x:auto;font-size:0.75rem;max-height:200px;"><code>${escHtml(JSON.stringify(s.schema||s,null,2))}</code></pre>`;
        });
    }
    html += '</div>';
    c.innerHTML = html;
}

async function validateSchema() {
    if (!_lastGeneratedSchema) { alert('Generate a schema first'); return; }
    const c = document.getElementById('schemaResults');
    const existingHtml = c.innerHTML;
    c.innerHTML += '<div class="seo-loading" id="validatingMsg"><i class="fas fa-spinner fa-spin"></i><p>Validating schema...</p></div>';
    const resp = await seoApi('v4_validate_schema', {schema: _lastGeneratedSchema});
    const vm = document.getElementById('validatingMsg'); if (vm) vm.remove();
    if (!resp.success) { c.innerHTML += '<p style="color:red;">Validation failed: '+escHtml(resp.message||'Unknown')+'</p>'; return; }
    const v = resp.data;
    let html = '<div style="margin-top:16px;padding:16px;border-radius:10px;background:'+(v.valid?'#f0fdf4;border:1px solid #86efac':'#fef2f2;border:1px solid #fca5a5')+'">';
    html += '<h4 style="margin-bottom:8px;"><i class="fas '+(v.valid?'fa-check-circle" style="color:#22c55e"':'fa-times-circle" style="color:#ef4444"')+'"></i> Schema Validation — Score: '+v.score+'/100</h4>';
    if (v.rich_result_eligible) html += '<div style="margin-bottom:8px;"><span class="issue-badge" style="background:#dcfce7;color:#15803d;"><i class="fas fa-star"></i> Eligible for Google Rich Results ('+escHtml(v.rich_result_type)+')</span></div>';
    else html += '<div style="margin-bottom:8px;"><span class="issue-badge" style="background:#fee2e2;color:#dc2626;"><i class="fas fa-times"></i> '+escHtml(v.rich_result_type || 'Not eligible for rich results')+'</span></div>';
    if (v.errors && v.errors.length) {
        html += '<div style="margin-bottom:8px;"><strong style="color:#dc2626;">Errors:</strong><ul style="margin:4px 0 0 16px;">';
        v.errors.forEach(e => html += '<li style="color:#dc2626;font-size:0.82rem;">'+escHtml(e)+'</li>');
        html += '</ul></div>';
    }
    if (v.warnings && v.warnings.length) {
        html += '<div><strong style="color:#d97706;">Warnings:</strong><ul style="margin:4px 0 0 16px;">';
        v.warnings.forEach(w => html += '<li style="color:#d97706;font-size:0.82rem;">'+escHtml(w)+'</li>');
        html += '</ul></div>';
    }
    if (v.valid && !v.errors?.length && !v.warnings?.length) html += '<p style="color:#22c55e;font-weight:600;margin:0;">All checks passed — schema is complete and valid.</p>';
    html += '</div>';
    c.innerHTML += html;
}

async function applySchema() {
    if (!_lastGeneratedSchema || !_lastSchemaContentId) { alert('Generate a schema first and select content'); return; }
    if (!confirm('This will save the generated schema to the database and inject it automatically into the page. Continue?')) return;
    const c = document.getElementById('schemaResults');
    c.innerHTML += '<div class="seo-loading" id="applyingMsg"><i class="fas fa-spinner fa-spin"></i><p>Applying schema to page...</p></div>';
    const resp = await seoApi('v4_apply_schema', {content_id: _lastSchemaContentId, schema: _lastGeneratedSchema});
    const am = document.getElementById('applyingMsg'); if (am) am.remove();
    let html = '<div style="margin-top:12px;padding:12px 16px;border-radius:8px;'+(resp.success?'background:#f0fdf4;border:1px solid #86efac;color:#15803d':'background:#fef2f2;border:1px solid #fca5a5;color:#dc2626')+';font-weight:600;">';
    html += '<i class="fas '+(resp.success?'fa-check-circle':'fa-times-circle')+'"></i> '+escHtml(resp.message||'Unknown result');
    html += '</div>';
    c.innerHTML += html;
}
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
