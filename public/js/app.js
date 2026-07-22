// ── VibeBoard — Project Dashboard ───────────────────────────────────
// Brain/Worker orchestration control plane.

(function () {
    'use strict';

    const API_BASE = '/api';

    function $(id) { return document.getElementById(id); }

    // ── Element references ──────────────────────────────────────────────
    const els = {
        // Navigation
        brand:           $('project-name'),
        projectDropdown: $('project-dropdown'),
        projectDropdownBtn: $('project-dropdown-btn'),
        projectDropdownList: $('project-list'),
        parkStatus:      $('park-status'),
        manageProjectsBtn: $('manage-projects-btn'),
        btnPark:         $('btn-park'),
        // Metrics
        totalTasks:      $('stat-total'),
        completedTasks:  $('stat-done'),
        inProgressTasks: $('stat-inprogress'),
        backlogTasks:    $('stat-backlog'),
        progressPct:     $('stat-progress'),
        // Chart
        chartCanvas:     $('donutChart'),
        burndownChart:   $('burndownChart'),
        donutChart:      $('donutChart'),
        // Workers
        workersGrid:     $('workers-grid'),
        workerCount:     $('worker-count-label'),
        manageAgentsBtn: $('manage-agents-btn'),
        // Agents modal
        agentsModal:         $('manage-agents-modal'),
        agentsModalClose:    $('manage-agents-modal-close'),
        agentsTbody:         $('manage-agents-tbody'),
        agentsTable:         $('manage-agents-table'),
        btnNewAgent:         $('btn-new-agent'),
        newAgentForm:        $('new-agent-form'),
        newAgentName:        $('new-agent-name'),
        newAgentRole:        $('new-agent-role'),
        newAgentModel:       $('new-agent-model'),
        newAgentProvider:    $('new-agent-provider'),
        newAgentToolset:     $('new-agent-toolset'),
        newAgentStatus:      $('new-agent-status'),
        createAgentSubmit:   $('create-agent-submit'),
        createAgentCancel:   $('create-agent-cancel'),
        // Task table
        taskTbody:    $('task-tbody'),
        taskCount:    $('task-count-label'),
        btnRefresh:   $('btn-refresh'),
        btnNewTask:   $('btn-new-task'),
        btnStartWorkers: $('btn-start-workers'),
        btnStopWorkers:  $('btn-stop-workers'),
        // Task modal
        modal:            $('task-modal'),
        modalTitle:       $('modal-title'),
        modalClose:       $('modal-close'),
        taskForm:         $('task-form'),
        taskId:           $('task-id'),
        taskTitle:        $('task-title'),
        taskDescription:  $('task-description'),
        taskStatus:       $('task-status'),
        taskAssignedTo:   $('task-assigned-to'),
        formSubmit:       $('form-submit'),
        formCancel:       $('form-cancel'),
        submitBtnText:    document.querySelector('#form-submit .btn-text'),
        // Pipeline
        pipelineColumns: $('pipeline-columns'),
        pipelineTaskCount: $('pipeline-task-count'),
        plPlan:          $('pl-plan'),
        plSpec:          $('pl-spec'),
        plAssess:        $('pl-assess'),
        plCode:          $('pl-code'),
        plTest:          $('pl-test'),
        plReview:        $('pl-review'),
        plDone:          $('pl-done'),
        // Stories
        storiesGrid:     $('stories-grid'),
        storiesCount:    $('stories-count'),
        btnNewStory:     $('btn-new-story'),
        btnGhIntegrate:  $('btn-gh-integrate'),
        // Reports
        reportGrid:            $('report-grid'),
        reportAvgRegressions:  $('report-avg-regressions'),
        reportAvgDuration:    $('report-avg-duration'),
        reportAvgComplexity:  $('report-avg-complexity'),
        reportRegressedTasks: $('report-regressed_tasks'),
        reportTotalStories:   $('report-total-stories'),
        // Task form new fields
        taskComplexity:  $('task-complexity'),
        taskStoryUrl:    $('task-story-url'),
        taskStoryId:     $('task-story-id'),
        taskDependsOn:  $('task-depends-on'),
        logPanel:       $('execution-log-panel'),
        logContainer:   $('log-container'),
        logTaskId:      $('log-task-id'),
        // Stories
        storiesGrid:     $('stories-grid'),
        storiesCount:    $('stories-count'),
        btnNewStory:     $('btn-new-story'),
        btnGhIntegrate:  $('btn-gh-integrate'),
        // Story modal
        storyModal:      $('story-modal'),
        storyForm:       $('story-form'),
        storyTitle:      $('story-title'),
        storyDescription: $('story-description'),
        storyType:       $('story-type'),
        storyComplexity: $('story-complexity'),
        storyCancel:     $('story-cancel'),
        storyClose:      $('story-modal-close'),
        storySubmit:     $('story-submit'),
        // Reports
        reportGrid:            $('report-grid'),
        reportAvgRegressions:  $('report-avg-regressions'),
        reportAvgDuration:    $('report-avg-duration'),
        reportAvgComplexity:  $('report-avg-complexity'),
        reportRegressedTasks: $('report-regressed-tasks'),
        reportTotalStories:   $('report-total-stories'),
        // Task form new fields
        taskComplexity:  $('task-complexity'),
        taskStoryUrl:    $('task-story-url'),
        taskStoryId:     $('task-story-id'),
        stagePanel:     $("pipeline-stage-panel"),
        stageBar:       $("pipeline-stage-bar"),
        stageLabel:     $("pipeline-stage-label"),
        // Park modal
        parkModal:         $('park-modal'),
        parkProjectName:   $('park-project-name'),
        parkPreview:       $('park-preview'),
        parkNote:          $('park-note'),
        parkConfirm:       $('park-confirm'),
        parkCancel:        $('park-cancel'),
        parkModalClose:    $('park-modal-close'),
        // Manage modal
        manageModal:        $('manage-modal'),
        manageTableTbody:   $('manage-project-tbody'),
        manageModalClose:   $('manage-modal-close'),
        btnNewProject:      $('btn-new-project'),
        newProjectForm:     $('new-project-form'),
        newProjectName:     $('new-project-name'),
        newProjectDesc:     $('new-project-desc'),
        createProjectSubmit: $('create-project-submit'),
        createProjectCancel: $('create-project-cancel'),
        // Toast
        toastContainer:  $('toast-container'),
    };

    let currentProjectId = null;
    let allProjects = [];
    let allWorkers = [];
    let sortCol = null;
    let sortDir = 'asc';
    let isRefreshing = false;
    let createdByFilter = 'all';
    let allTasks = [];

    // ── Helpers ─────────────────────────────────────────────────────────
    function escHtml(s) {
        if (!s) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function showLoading(v) {
        const o = $('loading-overlay');
        if (o) o.classList.toggle('hidden', !v);
    }

    // ── Toast notifications ────────────────────────────────────────────
    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        els.toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // ── Data fetching ───────────────────────────────────────────────────
    async function fetchJSON(url) {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function fetchData() {
        try {
            const [tasks, metrics, workers, projects, currentProject, stories, reports] = await Promise.all([
                fetchJSON(`${API_BASE}/tasks`),
                fetchJSON(`${API_BASE}/metrics`),
                fetchJSON(`${API_BASE}/workers`),
                fetchJSON(`${API_BASE}/projects`),
                fetchJSON(`${API_BASE}/projects/current`).catch(() => null),
                fetchJSON(`${API_BASE}/stories`).catch(() => []),
                fetchJSON(`${API_BASE}/reports/stats`).catch(() => null),
            ]);

            allWorkers = workers;
            allProjects = projects;

            if (currentProject) {
                currentProjectId = currentProject.id;
                if (els.brand) {
                    els.brand.classList.add('is-updating');
                    requestAnimationFrame(() => {
                        els.brand.textContent = currentProject.name || 'VibeBoard';
                        els.brand.classList.remove('is-updating');
                        els.brand.classList.add('loaded');
                    });
                }
                if (currentProject.parked_at) {
                    if (els.parkStatus) els.parkStatus.classList.remove('hidden');
                } else {
                    if (els.parkStatus) els.parkStatus.classList.add('hidden');
                }
            }

            renderMetrics(metrics);
            // Burndown chart
            try {
                const bdRes = await fetch(`${API_BASE}/burndown`);
                const bd = await bdRes.json();
                if (bd && bd.points) renderBurndownChart(bd);
            } catch (e) { console.warn('Burndown fetch failed', e); }
            allTasks = tasks;
            renderTasks(tasks);
            renderWorkers(workers);
            renderProjectDropdown(projects, currentProject);
            // Store tasks globally for depends_on dropdown
            window.__allTasks = tasks;
            // Pipeline + Stories + Reports
            renderPipeline(tasks);
            renderStories(stories, tasks);
            renderReports(reports);
        } catch (err) {
            showToast('Failed to load data', 'error');
        }
    }

    function reloadAll() {
        if (isRefreshing) return;
        isRefreshing = true;
        const btn = els.btnRefresh;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-sm"></span>';
        Promise.all([fetchData(), loadProjects()]).finally(() => {
            isRefreshing = false;
            btn.disabled = false;
            btn.textContent = 'Refresh';
        });
    }

    // ── Metrics ─────────────────────────────────────────────────────────
    function renderMetrics(data) {
        if (!data) return;
        const b = data.breakdown || {};
        const total = (b.Plan || 0) + (b.Spec || 0) + (b.Assess || 0) + (b.Code || 0) + (b.Test || 0) + (b.Review || 0) + (b.Done || 0);
        els.totalTasks.textContent = total;
        els.completedTasks.textContent = b.Done || 0;
        els.inProgressTasks.textContent = (b.Plan || 0) + (b.Spec || 0) + (b.Assess || 0) + (b.Code || 0) + (b.Test || 0) + (b.Review || 0);
        els.backlogTasks.textContent = b.Plan || 0;
        els.progressPct.textContent = (data.progressPercentage != null) ? data.progressPercentage + '%' : '0%';
        renderChart(b);
    }

    // ── Chart ───────────────────────────────────────────────────────────
    let __chartInstance = null;
    function renderChart(breakdown) {
        const canvas = els.chartCanvas;
        if (!canvas) return;

        // The breakdown is still used to satisfy the requirement of "receiving the same breakdown"
        const labels = Object.keys(breakdown);
        const values = labels.map(l => breakdown[l]);
        const colors = {
            'Plan':     '#e67e22',
            'Spec':     '#5dade2',
            'Assess':   '#154360',
            'Code':     '#27ae60',
            'Test':     '#8e44ad',
            'Review':   '#00bcd4',
            'Done':     '#7f8c8d'
        };
        const bg = labels.map(l => colors[l] || '#8b949e');

        if (__chartInstance) __chartInstance.destroy();
        const ctx = canvas.getContext('2d');
        __chartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: bg,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#8b949e',
                            padding: 12,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1c2128',
                        titleColor: '#e6edf3',
                        bodyColor: '#e6edf3',
                        borderColor: '#30363d',
                        borderWidth: 1
                    }
                }
            }
        });
    }

    // ── Burndown Chart ───────────────────────────────────────────────────
    function renderBurndownChart(burndown) {
        const canvas = els.burndownChart;
        if (!canvas || !burndown || !burndown.points) return;
        const ctx = canvas.getContext('2d');
        const labels = burndown.points.map(p => {
            const d = new Date(p.date + 'T00:00:00');
            return d.toLocaleDateString('en-GB', {day:'numeric', month:'short'});
        });
        const remaining = burndown.points.map(p => p.remaining);
        const ideal = burndown.ideal || [];

        if (canvas._chart) canvas._chart.destroy();
        canvas._chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Remaining',
                        data: remaining,
                        borderColor: '#8eb4d4', // Changed to steel blue as requested
                        backgroundColor: 'rgba(142, 180, 212, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: '#8eb4d4',
                        borderWidth: 2
                    },
                    {
                        label: 'Ideal',
                        data: ideal,
                        borderColor: '#8b949e',
                        borderDash: [5, 5],
                        borderWidth: 1.5,
                        pointRadius: 0,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#8b949e', padding: 12, font: { size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: '#1c2128',
                        titleColor: '#e6edf3',
                        bodyColor: '#e6edf3',
                        borderColor: '#30363d',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        ticks: { color: '#8b949e', font: { size: 11 } },
                        grid: { color: '#21262d' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#8b949e', font: { size: 11 } },
                        grid: { color: '#21262d' }
                    }
                }
            }
        });
    }

    // ── Chart tab switching ──────────────────────────────────────────────
    document.querySelectorAll('.chart-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.chart-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const type = tab.dataset.chart;
            document.getElementById('burndown-wrapper').style.display = type === 'burndown' ? '' : 'none';
            document.getElementById('donut-wrapper').style.display = type === 'donut' ? '' : 'none';
            if (type === 'burndown' && els.burndownChart._chart) {
                els.burndownChart._chart.resize();
            } else if (type === 'donut' && __chartInstance) {
                __chartInstance.resize();
            }
        });
    });

    // ── Tasks ────────────────────────────────────────────────────────────
    function sortTasks(tasks) {
        if (!sortCol || !Array.isArray(tasks) || tasks.length < 2) return tasks;
        const sorted = [...tasks];
        sorted.sort((a, b) => {
            let va, vb;
            switch (sortCol) {
                case 'id':      va = a.id; vb = b.id; break;
                case 'title':   va = (a.title || '').toLowerCase(); vb = (b.title || '').toLowerCase(); break;
                case 'status':  va = (a.status || '').toLowerCase(); vb = (b.status || '').toLowerCase(); break;
                case 'worker':  va = (a.worker_name || '').toLowerCase(); vb = (b.worker_name || '').toLowerCase(); break;
                case 'created': va = a.created_at || ''; vb = b.created_at || ''; break;
                default: return 0;
            }
            if (va < vb) return sortDir === 'asc' ? -1 : 1;
            if (va > vb) return sortDir === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    }

    function handleSort(col) {
        if (sortCol === col) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortCol = col;
            sortDir = 'asc';
        }
        document.querySelectorAll('.sort-arrow').forEach(a => a.className = 'sort-arrow');
        const th = document.querySelector(`[data-sort="${col}"]`);
        if (th) th.querySelector('.sort-arrow').className = 'sort-arrow ' + sortDir;
        fetchData();
    }

    function renderTasks(tasks) {
        if (!Array.isArray(tasks)) tasks = [];
        let filtered = createdByFilter === 'all'
            ? tasks
            : tasks.filter(t => (t.created_by || 'user') === createdByFilter);
        filtered = sortTasks(filtered);
        els.taskCount.textContent = filtered.length + ' task' + (filtered.length !== 1 ? 's' : '');

        if (filtered.length === 0) {
            els.taskTbody.innerHTML = '<tr class="loading-row"><td colspan="9">No tasks yet. Create one!</td></tr>';
            return;
        }

        const statusColors = {
            'Plan': '#e67e22',
            'Spec': '#5dade2',
            'Assess': '#154360',
            'Code': '#27ae60',
            'Test': '#8e44ad',
            'Review': '#00bcd4',
            'Done': '#7f8c8d',
        };

        els.taskTbody.innerHTML = filtered.map(t => {
            const sc = statusColors[t.status] || '#8b949e';
            const creator = (t.created_by || 'user');
            const creatorBadgeClass = creator === 'user' ? 'user' : 'agent';
            const creatorLabel = creator === 'user' ? 'Usr' : creator.slice(0,3).toUpperCase();
            // Dependencies badges
            let depsHtml = '—';
            if (t.depends_on) {
                try {
                    const deps = JSON.parse(t.depends_on);
                    if (deps && deps.length > 0) {
                        depsHtml = deps.map(d => `<span class="dep-badge">#${d}</span>`).join(' ');
                    }
                } catch(e) {}
            }
            // Retry info
            const retries = parseInt(t.retry_count || 0);
            const maxRetries = parseInt(t.max_retries || 3);
            let retryHtml = '';
            if (t.status === 'Failed' || retries > 0) {
                if (retries < maxRetries) {
                    retryHtml = `<button class="btn-retry" onclick="handleRetry(${t.id})">↻ ${retries}/${maxRetries}</button>`;
                } else {
                    retryHtml = `<span class="retry-maxed">✕ ${retries}/${maxRetries}</span>`;
                }
            } else {
                retryHtml = `<span class="retry-count">${retries}/${maxRetries}</span>`;
            }
            return `<tr>
                <td>${escHtml(t.id)}</td>
                <td class="col-title">${escHtml(t.title)}</td>
                <td class="col-type"><span class="badge-created-by ${creatorBadgeClass}">${creatorLabel}</span></td>
                <td><span class="status-badge" style="background:${sc};color:#0d1117">${escHtml(t.status || 'Plan')}</span></td>
                <td>${escHtml(t.worker_name || '—')}</td>
                <td>${depsHtml}</td>
                <td>${retryHtml}</td>
                <td>${t.created_at ? new Date(t.created_at).toLocaleDateString('en-GB') : '—'}</td>
                <td class="col-actions">
                    <button class="btn btn-sm btn-secondary" onclick="editTask(${t.id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteTask(${t.id})">Delete</button>
                </td>
            </tr>`;
        }).join('');
    }

    window.editTask = async function (id) {
        try {
            showLoading(true);
            const res = await fetch(`${API_BASE}/tasks/${id}`);
            if (!res.ok) throw new Error('Not found');
            const task = await res.json();
            openModal('Edit Task', task);
        } catch (err) {
            showToast('Failed to load task details', 'error');
        } finally {
            showLoading(false);
        }
    };

    window.deleteTask = async function (id) {
        if (!confirm('Delete this task?')) return;
        try {
            showLoading(true);
            const res = await fetch(`${API_BASE}/tasks/${id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error('Delete failed');
            showToast('Task deleted', 'success');
            await fetchData();
        } catch (err) {
            showToast('Failed to delete task', 'error');
        } finally {
            showLoading(false);
        }
    };

    // ── Task modal ──────────────────────────────────────────────────────
    function openModal(title, task) {
        els.modalTitle.textContent = title;
        els.taskId.value = task ? task.id : '';
        els.taskTitle.value = task ? task.title : '';
        els.taskDescription.value = task ? task.description : '';
        els.taskStatus.value = task ? task.status : 'Plan';
        // Populate new fields
        if (els.taskComplexity) els.taskComplexity.value = task ? (task.complexity || '3') : '3';
        if (els.taskStoryUrl) els.taskStoryUrl.value = task ? (task.story_url || '') : '';
        if (els.taskStoryId) {
            els.taskStoryId.value = task ? (task.story_id || '') : '';
            // Populate story dropdown
            populateStoryDropdown(task ? task.story_id : null);
        }
        // Populate agent dropdown
        populateAgentDropdown(task ? task.assigned_to : null);
        // Populate depends_on multi-select
        populateDependsOn(task, allWorkers);
        // Show/hide execution log panel
        if (task && task.execution_log) {
            showExecutionLog(task);
        } else {
            els.logPanel.classList.add('hidden');
        // Show/hide pipeline stage indicator
        renderPipelineStages(task);
        }
        els.submitBtnText.textContent = task ? 'Update Task' : 'Create Task';
        els.modal.classList.remove('hidden');
        els.taskTitle.focus();
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.taskForm.reset();
        els.taskId.value = '';
    }

    function populateAgentDropdown(selectedId) {
        const sel = els.taskAssignedTo;
        if (!sel) return;
        sel.innerHTML = '<option value="">— No agent —</option>';
        (allWorkers || []).forEach(w => {
            const opt = document.createElement('option');
            opt.value = w.id;
            opt.textContent = w.name + (w.role ? ' (' + w.role + ')' : '');
            if (selectedId && String(w.id) === String(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function populateDependsOn(task, workers) {
        const sel = els.taskDependsOn;
        if (!sel) return;
        sel.innerHTML = '';
        const allTasks = window.__allTasks || [];
        const currentId = task ? parseInt(task.id) : null;
        const currentDeps = task && task.depends_on ? (() => { try { return JSON.parse(task.depends_on); } catch(e) { return []; } })() : [];
        const depSet = new Set(currentDeps.map(d => parseInt(d)));
        allTasks.forEach(t => {
            if (currentId && parseInt(t.id) === currentId) return;
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.textContent = '#' + t.id + ' ' + (t.title || '').slice(0, 60);
            if (depSet.has(parseInt(t.id))) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    function showExecutionLog(task) {
        els.logPanel.classList.remove('hidden');
        const taskId = task.id || '';
        els.logTaskId.textContent = 'Task #' + taskId;
        els.logContainer.innerHTML = '';
        if (!task.execution_log) {
            els.logContainer.innerHTML = '<div class="log-empty">No execution log entries yet.</div>';
            return;
        }
        try {
            const entries = JSON.parse(task.execution_log);
            if (!Array.isArray(entries) || entries.length === 0) {
                els.logContainer.innerHTML = '<div class="log-empty">No execution log entries yet.</div>';
                return;
            }
            entries.forEach(entry => {
                const div = document.createElement('div');
                div.className = 'log-entry';
                div.innerHTML = '<span class="log-timestamp">' + escHtml(entry.timestamp || '') + '</span><span class="log-message">' + escHtml(entry.message || '') + '</span>';
                els.logContainer.appendChild(div);
            });
        } catch (e) {
            els.logContainer.innerHTML = '<div class="log-empty">Failed to parse log entries.</div>';
        }
    }

    // ── Pipeline View (US-24) ─────────────────────────────────────────

    // ── Pipeline Stage Inference (US-24) ──────────────────────────────
    var PIPELINE_STAGES = ["Plan", "Spec", "Assess", "Code", "Test", "Review", "Done"];

    function getTaskPipelineStage(task) {
        if (!task) return null;
        // Direct 1:1 mapping — statuses are now the 7 stages
        if (task.status === "Done") return "Done";
        if (task.status === "Review") return "Review";
        if (task.status === "Test") return "Test";
        if (task.status === "Code") return "Code";
        if (task.status === "Assess") return "Assess";
        if (task.status === "Spec") return "Spec";
        if (task.status === "Plan") return null; // Plan is the default/start
        // Fallback for legacy status values
        if (task.status === "QA-Review") return "Review";
        if (task.status === "In Progress") {
            if (!task.execution_log) return "Plan";
            try {
                var entries = JSON.parse(task.execution_log);
                if (!Array.isArray(entries) || entries.length === 0) return "Plan";
                var latestEvent = entries[entries.length - 1];
                var msg = (latestEvent.message || latestEvent.event || JSON.stringify(latestEvent)).toLowerCase();
                if (msg.indexOf("dispatched") !== -1 || msg.indexOf("plan") !== -1) return "Plan";
                if (msg.indexOf("spec") !== -1) return "Spec";
                if (msg.indexOf("assess") !== -1) return "Assess";
                if (msg.indexOf("code") !== -1 || msg.indexOf("file_created") !== -1 || msg.indexOf("implement") !== -1) return "Code";
                if (msg.indexOf("test") !== -1) return "Test";
                if (msg.indexOf("review") !== -1 || msg.indexOf("verified") !== -1 || msg.indexOf("qa") !== -1) return "Review";
                if (msg.indexOf("done") !== -1 || msg.indexOf("complete") !== -1) return "Done";
                return "Plan";
            } catch(e) {
                return "Plan";
            }
        }
        return null;
    }

    function getStageIndex(stageName) {
        if (!stageName) return -1;
        for (var i = 0; i < PIPELINE_STAGES.length; i++) {
            if (PIPELINE_STAGES[i] === stageName) return i;
        }
        return -1;
    }

    function renderPipelineStages(task) {
        if (!task) {
            els.stagePanel.classList.add("hidden");
            return;
        }
        var stage = getTaskPipelineStage(task);
        if (!stage) {
            els.stagePanel.classList.add("hidden");
            return;
        }
        els.stagePanel.classList.remove("hidden");
        var stageIdx = getStageIndex(stage);
        if (stageIdx < 0) {
            els.stagePanel.classList.add("hidden");
            return;
        }
        els.stageLabel.textContent = "Stage " + (stageIdx + 1) + " of 7 - " + stage;
        var steps = els.stageBar.querySelectorAll(".pipeline-stage-step");
        steps.forEach(function(step, idx) {
            step.classList.remove("completed", "active");
            if (idx < stageIdx) {
                step.classList.add("completed");
            } else if (idx === stageIdx) {
                step.classList.add("active");
            }
        });
    }
    function pipelineCardStageDots(t) {
        var stage = getTaskPipelineStage(t);
        if (!stage) return "";
        var stageIdx = getStageIndex(stage);
        if (stageIdx < 0) return "";
        var dots = "";
        for (var i = 0; i < PIPELINE_STAGES.length; i++) {
            var cls = "pipeline-card-stage-dot";
            if (i < stageIdx) cls += " completed";
            else if (i === stageIdx) cls += " active";
            dots += '<div class="' + cls + '"></div>';
        }
        return '<div class="pipeline-card-stages">' + dots + '</div>';
    }

    function renderPipeline(tasks) {
        if (!Array.isArray(tasks)) tasks = [];
        const stages = {
            'Plan': els.plPlan,
            'Spec': els.plSpec,
            'Assess': els.plAssess,
            'Code': els.plCode,
            'Test': els.plTest,
            'Review': els.plReview,
            'Done': els.plDone,
        };
        const stageCounters = { 'Plan': 0, 'Spec': 0, 'Assess': 0, 'Code': 0, 'Test': 0, 'Review': 0, 'Done': 0 };
        tasks.forEach(t => {
            const stage = t.status || 'Plan';
            if (stageCounters[stage] !== undefined) stageCounters[stage]++;
        });
        $('count-plan').textContent = stageCounters['Plan'];
        $('count-spec').textContent = stageCounters['Spec'];
        $('count-assess').textContent = stageCounters['Assess'];
        $('count-code').textContent = stageCounters['Code'];
        $('count-test').textContent = stageCounters['Test'];
        $('count-review').textContent = stageCounters['Review'];
        $('count-done').textContent = stageCounters['Done'];
        els.pipelineTaskCount.textContent = tasks.length + ' task' + (tasks.length !== 1 ? 's' : '');

        Object.entries(stages).forEach(([stage, container]) => {
            if (!container) return;
            const stageTasks = tasks.filter(t => (t.status || 'Plan') === stage);
            if (stageTasks.length === 0) {
                container.innerHTML = '<div class="pipeline-empty">No tasks</div>';
                return;
            }
            container.innerHTML = stageTasks.map((t, idx) => {
                let depsHtml = '';
                if (t.depends_on) {
                    try {
                        const deps = JSON.parse(t.depends_on);
                        if (deps && deps.length > 0) {
                            depsHtml = '<div class="pipeline-card-deps">Depends on: #' + deps.join(', #') + '</div>';
                        }
                    } catch(e) {}
                }
                // Show full sequential number for backlog items, DB id for others
                const numHtml = stage === 'Plan'
                    ? '<span class="backlog-num">' + (idx + 1) + '</span>'
                    : '<span>#' + t.id + '</span>';
                return '<div class="pipeline-card" draggable="true" data-task-id="' + t.id + '" onclick="editTask(' + t.id + ')">' +
                    '<div class="pipeline-card-top">' +
                    numHtml +
                    '<div class="pipeline-card-title">' + escHtml(t.title) + '</div>' +
                    '</div>' +
                    '<div class="pipeline-card-meta">' +
                    (t.worker_name ? '<span class="badge-worker">' + escHtml(t.worker_name) + '</span>' : '') +
                    '</div>' +
                    depsHtml +
                    pipelineCardStageDots(t) +
                    '</div>';
            }).join('');
        });
    }

    // ── Drag & Drop for Pipeline (kanban) ─────────────────────────────
    function initDragDrop() {
        const cols = document.querySelectorAll('.pipeline-col');
        cols.forEach(col => {
            col.addEventListener('dragover', e => e.preventDefault());
            col.addEventListener('dragenter', e => {
                e.preventDefault();
                e.currentTarget.classList.add('drag-over');
            });
            col.addEventListener('dragleave', e => {
                e.currentTarget.classList.remove('drag-over');
            });
            col.addEventListener('drop', handleDrop);
        });

        // Drag-over highlighting on worker cards when dragging a task
        const workersGrid = els.workersGrid;
        if (workersGrid) {
            workersGrid.addEventListener('dragenter', e => {
                const card = e.target.closest('.worker-card');
                if (card) card.classList.add('selected');
            });
            workersGrid.addEventListener('dragleave', e => {
                const card = e.target.closest('.worker-card');
                if (card && !card.contains(e.relatedTarget)) {
                    card.classList.remove('selected');
                }
            });
            workersGrid.addEventListener('drop', e => {
                const card = e.target.closest('.worker-card');
                if (card) card.classList.remove('selected');
            });
        }

        // Delegated events on the pipeline container for card drags
        const container = els.pipelineColumns;
        container.addEventListener('dragstart', e => {
            const card = e.target.closest('.pipeline-card');
            if (!card) return;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.dataset.taskId);
            card.classList.add('dragging');
        });
        container.addEventListener('dragend', e => {
            const card = e.target.closest('.pipeline-card');
            if (card) card.classList.remove('dragging');
            document.querySelectorAll('.pipeline-col.drag-over').forEach(el => el.classList.remove('drag-over'));
            document.querySelectorAll('.worker-card.selected').forEach(el => el.classList.remove('selected'));
        });
    }

    // ── Frame Select (rubber-band multi-select) ────────────────────────
    let selectedTaskIds = new Set();
    let isSelecting = false;
    let selectStart = null;
    let selRect = null;

    function initFrameSelect() {
        selRect = document.getElementById('selection-rect');
        const pipeline = els.pipelineColumns;
        if (!pipeline || !selRect) return;

        pipeline.addEventListener('mousedown', function (e) {
            // Ignore if clicking a card, the rect, inside inputs, or right-click
            if (e.button !== 0) return;
            if (e.target.closest('.pipeline-card') || e.target.closest('button') || e.target.closest('input')) return;
            if (e.target === selRect) return;

            isSelecting = true;
            const rect = pipeline.getBoundingClientRect();
            selectStart = {
                x: e.clientX - rect.left,
                y: e.clientY - rect.top,
            };
            selRect.style.left = selectStart.x + 'px';
            selRect.style.top = selectStart.y + 'px';
            selRect.style.width = '0px';
            selRect.style.height = '0px';
            selRect.classList.add('active');
        });

        document.addEventListener('mousemove', function (e) {
            if (!isSelecting) return;
            const rect = pipeline.getBoundingClientRect();
            const curX = e.clientX - rect.left;
            const curY = e.clientY - rect.top;

            const left = Math.min(selectStart.x, curX);
            const top = Math.min(selectStart.y, curY);
            const w = Math.abs(curX - selectStart.x);
            const h = Math.abs(curY - selectStart.y);

            selRect.style.left = left + 'px';
            selRect.style.top = top + 'px';
            selRect.style.width = w + 'px';
            selRect.style.height = h + 'px';
        });

        document.addEventListener('mouseup', function (e) {
            if (!isSelecting) return;
            isSelecting = false;
            // Capture rect BEFORE hiding (class removal -> display:none -> zero dims)
            const rb = selRect.getBoundingClientRect();
            const pipeline = els.pipelineColumns;

            selRect.classList.remove('active');

            // If the rect is tiny (< 5px), treat as clear-selection click
            if (rb.width < 5 && rb.height < 5) {
                clearSelection();
                return;
            }

            // Hit-test: find cards inside the selection rect
            const cards = pipeline.querySelectorAll('.pipeline-card');
            const newlySelected = [];
            cards.forEach(card => {
                const cr = card.getBoundingClientRect();
                if (rectsOverlap(rb, cr)) {
                    newlySelected.push(card.dataset.taskId);
                    card.classList.add('selected');
                }
            });

            // Replace selection entirely (not additive per drag-select UX)
            clearSelection(false);
            newlySelected.forEach(id => {
                selectedTaskIds.add(parseInt(id));
                const card = pipeline.querySelector(`.pipeline-card[data-task-id="${id}"]`);
                if (card) card.classList.add('selected');
            });
        });
    }

    function rectsOverlap(a, b) {
        return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
    }

    function clearSelection(clearDom = true) {
        selectedTaskIds.clear();
        if (clearDom) {
            els.pipelineColumns.querySelectorAll('.pipeline-card.selected').forEach(el => el.classList.remove('selected'));
        }
    }

    async function handleDrop(e) {
        e.preventDefault();
        const col = e.currentTarget;
        col.classList.remove('drag-over');

        const taskId = e.dataTransfer.getData('text/plain');
        const newStage = col.dataset.stage;
        if (!taskId || !newStage) return;

        // ── Batch move: if dropped task is in the selection, move all ──
        const draggedId = parseInt(taskId);
        const isBatch = selectedTaskIds.has(draggedId) && selectedTaskIds.size > 1;

        if (isBatch) {
            // Only update ids whose current status differs from the target
            const toUpdate = Array.from(selectedTaskIds).filter(id => {
                const t = allTasks.find(t => t.id === id);
                return t && (t.status || 'Plan') !== newStage;
            });
            if (toUpdate.length === 0) {
                clearSelection();
                fetchData();
                return;
            }
            const batchBody = { ids: toUpdate, data: { status: newStage } };
            await fetch(`${API_BASE}/tasks/batch-update`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(batchBody),
            });
            // Reset workers for each moved task (if applicable)
            for (const id of toUpdate) {
                const t = allTasks.find(t => t.id === id);
                if (t && t.assigned_to) {
                    await fetch(`${API_BASE}/workers/${t.assigned_to}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status: 'idle' }),
                    });
                    const w = allWorkers.find(w => w.id === t.assigned_to);
                    if (w) w.status = 'idle';
                }
            }
            clearSelection();
            fetchData();
            return;
        }

        // ── Single-task move (existing logic) ─────────────────────────
        const task = allTasks.find(t => t.id == taskId);
        if (!task || (task.status || 'Plan') === newStage) return;

        const updateBody = { status: newStage };

        // If task was previously assigned to someone else, reset that worker
        const prevWorkerId = task.assigned_to;
        if (prevWorkerId && prevWorkerId != (updateBody.assigned_to || null)) {
            const prevWorker = allWorkers.find(w => w.id == prevWorkerId);
            if (prevWorker) {
                await fetch(`${API_BASE}/workers/${prevWorkerId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: 'idle' })
                });
                prevWorker.status = 'idle';
            }
        }

        // Auto-assign worker based on destination column
        if (newStage === 'Code' || newStage === 'Test' || newStage === 'Plan' || newStage === 'Spec' || newStage === 'Assess') {
            const idleWorker = allWorkers.find(w => w.status === 'idle');
            if (idleWorker) {
                updateBody.assigned_to = idleWorker.id;
            } else {
                showToast('No idle workers available to assign', 'warning');
            }
        } else if (newStage === 'Review') {
            const tester = allWorkers.find(w =>
                w.name.toLowerCase() === 'testerbot' ||
                w.role.toLowerCase().includes('qa') ||
                w.role.toLowerCase().includes('test')
            );
            if (tester) {
                updateBody.assigned_to = tester.id;
            }
        }

        try {
            // Update task status + assignment
            const res = await fetch(`${API_BASE}/tasks/${taskId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updateBody)
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            // Update worker status if assigned
            if (updateBody.assigned_to) {
                const worker = allWorkers.find(w => w.id === updateBody.assigned_to);
                if (worker) {
                    await fetch(`${API_BASE}/workers/${worker.id}`, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ status: 'busy' })
                    });
                    // Update local cache immediately so rapid drops don't double-assign
                    worker.status = 'busy';
                    let workerLabel = worker.name + (worker.model ? ' (' + worker.model + ')' : '');
                    showToast(`"${task.title}" → ${newStage} · assigned to ${workerLabel}`, 'success');
                }
            } else {
                showToast(`"${task.title}" → ${newStage}`, 'success');
            }

            fetchData();
        } catch (err) {
            showToast('Failed to move task: ' + err.message, 'error');
        }
    }

    // ── Swarm Activity (removed — replaced by Stories section) ────────
    function renderSwarm() { /* no longer used */ }

    // ── Stories / GitHub Integration ─────────────────────────────────
    function renderStories(stories, tasks) {
        const grid = els.storiesGrid;
        const count = els.storiesCount;
        if (!grid) return;
        if (!stories || stories.length === 0) {
            grid.innerHTML = '<div class="loading-row" style="justify-content:center;padding:24px"><span style="color:var(--text-muted)">No stories yet — click <strong>New Story</strong> to create one.</span></div>';
            if (count) count.textContent = '0 stories';
            return;
        }
        // Build task lookup for linked tasks
        const taskMap = {};
        (tasks || []).forEach(t => {
            if (t.story_id) {
                if (!taskMap[t.story_id]) taskMap[t.story_id] = [];
                taskMap[t.story_id].push(t);
            }
        });
        grid.innerHTML = stories.map(s => {
            const linked = taskMap[s.id] || [];
            const deptClass = s.department ? 'dept-' + s.department.toLowerCase() : '';
            const statusClass = s.status ? 'status-badge' : '';
            const statusColor = s.status ? getStatusColor(s.status === 'Done' ? 'Done' : 'In Progress') : 'var(--text-muted)';
            const ghLink = s.issue_url ? '<a href="' + escHtml(s.issue_url) + '" target="_blank" class="story-github-link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844a9.59 9.59 0 012.5.338c1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg> ' + (s.issue_number ? '#' + escHtml(s.issue_number) : 'View Issue') + '</a>' : '';
            const tasksHtml = linked.length > 0
                ? '<div class="story-tasks">Tasks: ' + linked.map(t => '<a href="#" class="task-link" onclick="event.preventDefault();editTask(' + t.id + ')">#' + t.id + '</a>').join('') + '</div>'
                : '';
            return '<div class="story-card' + (deptClass ? ' ' + deptClass : '') + '">' +
                '<div class="story-title">' +
                escHtml(s.title) +
                (s.id ? '<span class="story-id">#' + s.id + '</span>' : '') +
                '</div>' +
                (s.description ? '<div class="story-description">' + escHtml(s.description) + '</div>' : '') +
                '<div class="story-meta">' +
                (s.department ? '<span class="dept-badge" style="display:inline-block;padding:1px 6px;border-radius:10px;font-size:10px;color:#fff">' + escHtml(s.department) + '</span>' : '') +
                (s.status ? '<span class="status-badge" style="background:' + statusColor + ';color:#0d1117;font-size:10px;padding:1px 6px;border-radius:10px">' + escHtml(s.status) + '</span>' : '') +
                (ghLink ? '<span>' + ghLink + '</span>' : '') +
                '</div>' +
                tasksHtml +
                '</div>';
        }).join('');
        if (count) count.textContent = stories.length + ' storie' + (stories.length === 1 ? '' : 's');
    }

    // ── Reports / Analytics ──────────────────────────────────────────
    function renderReports(data) {
        const updateText = (id, val) => { const el = $(id); if (el) el.textContent = val; };
        if (!data || !data.stats) {
            updateText('report-avg-regressions', '—');
            updateText('report-avg-duration', '—');
            updateText('report-avg-complexity', '—');
            updateText('report-regressed-tasks', '—');
            updateText('report-total-stories', '—');
            return;
        }
        const s = data.stats;
        updateText('report-avg-regressions', s.avg_regressions != null ? Number(s.avg_regressions).toFixed(1) : '0');
        updateText('report-avg-duration', s.avg_duration || '—');
        updateText('report-avg-complexity', s.avg_complexity != null ? Number(s.avg_complexity).toFixed(1) + ' ★' : '—');
        updateText('report-regressed-tasks', s.regressed_count != null ? s.regressed_count : '0');
        updateText('report-total-stories', s.total_stories != null ? s.total_stories : '0');
    }

    // ── Story drop-down population ───────────────────────────────────
    function populateStoryDropdown(selectedId) {
        const sel = els.taskStoryId;
        if (!sel) return;
        // Fetch stories for the dropdown
        fetch(API_BASE + '/stories')
            .then(r => r.json())
            .then(stories => {
                sel.innerHTML = '<option value="">— None —</option>';
                if (Array.isArray(stories)) {
                    stories.forEach(s => {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = '#' + s.id + ' ' + (s.title || '').slice(0, 60);
                        if (selectedId && parseInt(s.id) === parseInt(selectedId)) opt.selected = true;
                        sel.appendChild(opt);
                    });
                }
            })
            .catch(() => {});
    }

    function getStatusColor(status) {
        switch (status) {
            case 'Plan': return '#a29bfe';
            case 'Spec': return '#00cec9';
            case 'Assess': return '#fd79a8';
            case 'Code': return '#74b9ff';
            case 'Test': return '#fdcb6e';
            case 'Review': return '#d9762b';
            case 'Done': return '#3fb950';
            case 'Failed': return '#f85149';
            case 'In Progress': return '#d29922'; // legacy/story status
            default: return '#8b949e';
        }
    }

    // ── Minion avatar generator ────────────────────────────────────────
    function minionSvg(name) {
        // Deterministic seed from name
        let h = 0;
        for (let i = 0; i < name.length; i++) h = ((h << 5) - h) + name.charCodeAt(i);
        const r = (n) => ((h >> (n * 4)) & 3) || n;
        const eyes = r(2) % 3 === 0 ? 1 : 2;    // 1/3 chance of single eye
        const eyeX = eyes === 1 ? 20 : [12, 28];
        const eyeColor = ['#3b2314','#4a7fb5','#5aa87a','#6b6b6b'][r(3) % 4];
        const hairStyle = (r(1) % 4);
        const mouth = r(4) % 3;

        // Hair SVG
        const hairs = [
            '', // bald
            '<path d="M10 8 Q15 3 20 8 Q25 3 30 8" fill="none" stroke="#222" stroke-width="2.5" stroke-linecap="round"/>', // spiky
            '<ellipse cx="20" cy="6" rx="10" ry="4" fill="#333"/><ellipse cx="17" cy="5" rx="4" ry="3" fill="#f5c542"/>', // tuft
            '<rect x="8" y="6" width="24" height="6" rx="3" fill="#222"/>', // flat cap
        ];
        // Mouth SVG
        const mouths = [
            '<path d="M13 28 Q20 32 27 28" fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/>', // smile
            '<ellipse cx="20" cy="30" rx="5" ry="3" fill="#333"/>', // open mouth
            '<path d="M14 29 Q20 32 26 29" fill="none" stroke="#333" stroke-width="1.5" stroke-linecap="round"/><circle cx="19" cy="27.5" r="1.5" fill="#333"/><circle cx="21" cy="27.5" r="1.5" fill="#333"/>', // wide grin
        ];

        let eyesSvg;
        if (eyes === 1) {
            eyesSvg = `<rect x="7" y="15" width="26" height="10" rx="3" fill="#444"/>
                <circle cx="20" cy="20" r="5" fill="white"/>
                <circle cx="20" cy="20" r="2.5" fill="${eyeColor}"/>
                <circle cx="20.5" cy="19.5" r="1" fill="white"/>`;
        } else {
            eyesSvg = `<rect x="6" y="15" width="28" height="10" rx="3" fill="#444"/>
                <circle cx="${eyeX[0]}" cy="20" r="4.5" fill="white"/>
                <circle cx="${eyeX[0]}" cy="20" r="2.5" fill="${eyeColor}"/>
                <circle cx="${eyeX[0]+0.5}" cy="19.5" r="1" fill="white"/>
                <circle cx="${eyeX[1]}" cy="20" r="4.5" fill="white"/>
                <circle cx="${eyeX[1]}" cy="20" r="2.5" fill="${eyeColor}"/>
                <circle cx="${eyeX[1]+0.5}" cy="19.5" r="1" fill="white"/>`;
        }

        // Initial letter badge on body
        const initial = name.charAt(0).toUpperCase();

        return `<svg viewBox="0 0 40 40" fill="none">
            <ellipse cx="20" cy="20" rx="17" ry="16" fill="#FFD700" stroke="#E6BE00" stroke-width="0.5"/>
            ${hairs[hairStyle]}
            ${eyesSvg}
            ${mouths[mouth]}
            <text x="20" y="37" text-anchor="middle" font-size="6" fill="#8b6914" font-weight="600">${initial}</text>
        </svg>`;
    }

    // ── Workers ────────────────────────────────────────────────────────
    function renderWorkers(workers) {
        if (!Array.isArray(workers)) workers = [];
        const active = workers.filter(w => w.status === 'busy');
        els.workerCount.textContent = active.length + ' active / ' + workers.length + ' total';

        if (workers.length === 0) {
            els.workersGrid.innerHTML = '<div class="loading-row"><td colspan="5">No workers configured.</td></div>';
            return;
        }

        els.workersGrid.innerHTML = workers.map(w => {
            const statusText = w.status === 'busy' ? 'Busy' : w.status === 'offline' ? 'Offline' : 'Idle';

            let taskHtml;
            if (w.task_id && w.task_title) {
                const taskStatusClass = (w.task_status || '').replace(/\s+/g, '-');
                taskHtml = `<div class="worker-task">
                    <svg class="worker-task-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                    </svg>
                    <span class="worker-task-title">${escHtml(w.task_title)}</span>
                    <span class="status-badge ${taskStatusClass}">${escHtml(w.task_status || 'Plan')}</span>
                </div>`;
            } else {
                taskHtml = `<div class="worker-idle-msg">No task assigned</div>`;
            }

            return `<div class="worker-card ${w.status}" data-worker-id="${w.id}">
                <div class="worker-card-top">
                    <div class="worker-avatar ${w.status}">${minionSvg(w.name)}</div>
                    <div class="worker-info">
                        <div class="worker-name-row">
                            <div class="worker-name">${escHtml(w.name)}</div>
                            <span class="worker-status-badge ${w.status}">${w.status === 'busy' ? '<span class="worker-busy-spinner"></span>' : ''}${statusText}</span>
                        </div>
                        <div class="worker-role">${escHtml(w.role)}</div>
                        ${w.model ? `<div class="worker-model">${escHtml(w.model)}</div>` : ''}
                    </div>
                </div>
                ${taskHtml}
            </div>`;
        }).join('');
    }

    // ── Worker polling (US-14: auto-refresh worker status) ─────────
    let workerPollInterval = null;

    async function pollWorkers() {
        try {
            const workers = await fetchJSON(`${API_BASE}/workers`);
            allWorkers = workers;
            renderWorkers(workers);
        } catch (err) {
            // silent — don't spam errors for background polls
        }
    }

    function startWorkerPolling() {
        if (workerPollInterval) return;
        // Poll workers every 5 seconds for near-real-time status updates
        workerPollInterval = setInterval(pollWorkers, 5000);
    }

    function stopWorkerPolling() {
        if (workerPollInterval) {
            clearInterval(workerPollInterval);
            workerPollInterval = null;
        }
    }

    // ── Agents modal (US-22) ──────────────────────────────────────────
    function openAgentsModal() {
        renderAgentsTable();
        els.agentsModal.classList.remove('hidden');
    }

    function closeAgentsModal() {
        els.agentsModal.classList.add('hidden');
        els.newAgentForm.classList.add('hidden');
    }

    async function renderAgentsTable() {
        try {
            const workers = await fetchJSON(`${API_BASE}/workers`);
            allWorkers = workers;
            els.agentsTbody.innerHTML = workers.map(w => {
                const statusColor = w.status === 'busy' ? '#d29922' : w.status === 'offline' ? '#f85149' : '#3fb950';
                return `<tr>
                    <td>${escHtml(w.name)}</td>
                    <td>${escHtml(w.role)}</td>
                    <td>${escHtml(w.model || '—')}</td>
                    <td>${escHtml(w.provider || '—')}</td>
                    <td>${escHtml(w.toolset || '—')}</td>
                    <td><span class="status-badge" style="background:${statusColor};color:#0d1117;font-size:11px">${escHtml(w.status)}</span></td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="deleteAgent(${w.id},'${escHtml(w.name)}')">Delete</button>
                    </td>
                </tr>`;
            }).join('');
        } catch (err) {
            els.agentsTbody.innerHTML = '<tr><td colspan="7">Failed to load agents</td></tr>';
        }
    }

    window.deleteAgent = async function (id, name) {
        if (!confirm(`Delete agent "${name}"?`)) return;
        try {
            const res = await fetch(`${API_BASE}/workers/${id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error('Delete failed');
            showToast('Agent deleted', 'success');
            await renderAgentsTable();
            await fetchData();
        } catch (err) {
            showToast('Failed to delete agent', 'error');
        }
    };

    async function createAgent() {
        const name = els.newAgentName.value.trim();
        if (!name) { showToast('Agent name required', 'error'); return; }
        const payload = {
            name,
            role: els.newAgentRole.value.trim() || 'Developer',
            model: els.newAgentModel.value.trim() || null,
            provider: els.newAgentProvider.value.trim() || null,
            toolset: els.newAgentToolset.value.trim() || null,
            status: els.newAgentStatus.value,
        };
        try {
            const res = await fetch(`${API_BASE}/workers`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('Create failed');
            showToast('Agent created', 'success');
            els.newAgentName.value = '';
            els.newAgentRole.value = '';
            els.newAgentModel.value = '';
            els.newAgentProvider.value = '';
            els.newAgentToolset.value = '';
            els.newAgentForm.classList.add('hidden');
            await renderAgentsTable();
            await fetchData();
        } catch (err) {
            showToast('Failed to create agent', 'error');
        }
    }

    // ── Projects ────────────────────────────────────────────────────────
    async function loadProjects() {
        try {
            allProjects = await fetchJSON(`${API_BASE}/projects`);
        } catch (err) {
            showToast('Failed to load projects', 'error');
        }
    }

    function renderProjectDropdown(projects, current) {
        if (!Array.isArray(projects) || projects.length === 0) {
            els.projectDropdownList.innerHTML = '<div class="dropdown-item disabled">No projects</div>';
            return;
        }
        const currentId = current ? current.id : null;
        els.projectDropdownList.innerHTML = projects.map(p => {
            const active = p.id === currentId;
            return `<div class="dropdown-item ${active ? 'active' : ''}" data-id="${p.id}">
                ${escHtml(p.name)}
                ${p.park_note ? '<span class="parked-badge">P</span>' : ''}
            </div>`;
        }).join('');

        els.projectDropdownList.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', async () => {
                const id = parseInt(item.dataset.id, 10);
                if (id === currentProjectId) { closeProjectDropdown(); return; }
                try {
                    showLoading(true);
                    await fetch(`${API_BASE}/projects/${id}/activate`, { method: 'POST' });
                    closeProjectDropdown();
                    await fetchData();
                } catch (err) {
                    showToast('Failed to switch project', 'error');
                } finally {
                    showLoading(false);
                }
            });
        });
    }

    function toggleProjectDropdown() {
        els.projectDropdown.classList.toggle('hidden');
        els.projectDropdownBtn.classList.toggle('open');
        els.projectDropdownBtn.setAttribute('aria-expanded', !els.projectDropdown.classList.contains('hidden'));
        if (!els.projectDropdown.classList.contains('hidden')) {
            renderProjectDropdown(allProjects, { id: currentProjectId });
        }
    }

    function closeProjectDropdown() {
        els.projectDropdown.classList.add('hidden');
        els.projectDropdownBtn.classList.remove('open');
        els.projectDropdownBtn.setAttribute('aria-expanded', 'false');
    }

    // ── Manage Projects modal ───────────────────────────────────────────
    async function openManageModal() {
        await loadProjects();
        renderManageTable();
        els.manageModal.classList.remove('hidden');
    }

    function renderManageTable() {
        if (!Array.isArray(allProjects) || allProjects.length === 0) {
            els.manageTableTbody.innerHTML = '<tr><td colspan="6">No projects</td></tr>';
            return;
        }
        els.manageTableTbody.innerHTML = allProjects.map(p => {
            const pct = p.progress != null ? Math.round(p.progress) : 0;
            const isActive = p.id === currentProjectId;
            return `<tr>
                <td>${escHtml(p.name)} ${isActive ? '<span class="active-badge">active</span>' : ''}</td>
                <td>${escHtml(p.status === 'parked' ? 'Parked' : 'Active')}</td>
                <td>${p.task_count || 0}</td>
                <td>
                    <div class="progress-bar-mini">
                        <div class="progress-fill" style="width:${pct}%"></div>
                    </div>
                    <span class="progress-text">${pct}%</span>
                </td>
                <td>${escHtml(p.park_note ? p.park_note.slice(0, 80) + '…' : '—')}</td>
                <td>
                    ${isActive ? '' : `<button class="btn btn-sm btn-primary manage-activate" data-id="${p.id}">Activate</button>`}
                    ${p.id !== currentProjectId ? `<button class="btn btn-sm btn-danger manage-delete" data-id="${p.id}">Delete</button>` : ''}
                </td>
            </tr>`;
        }).join('');

        // Activate buttons
        els.manageTableTbody.querySelectorAll('.manage-activate').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                try {
                    showLoading(true);
                    await fetch(`${API_BASE}/projects/${id}/activate`, { method: 'POST' });
                    showToast('Project activated', 'success');
                    await reloadAll();
                    await openManageModal();
                } catch (e) {
                    showToast('Failed to activate', 'error');
                } finally {
                    showLoading(false);
                }
            });
        });

        // Delete buttons
        els.manageTableTbody.querySelectorAll('.manage-delete').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                const p = allProjects.find(x => x.id === id);
                if (p && p.task_count > 0) {
                    if (!confirm(`Delete "${p.name}" with ${p.task_count} tasks?`)) return;
                } else {
                    if (!confirm(`Delete "${p.name}"?`)) return;
                }
                try {
                    showLoading(true);
                    await fetch(`${API_BASE}/projects/${id}`, { method: 'DELETE' });
                    showToast('Project deleted', 'success');
                    await reloadAll();
                    await openManageModal();
                } catch (e) {
                    showToast('Failed to delete', 'error');
                } finally {
                    showLoading(false);
                }
            });
        });
    }

    async function createProject() {
        const name = els.newProjectName.value.trim();
        if (!name) { showToast('Project name required', 'error'); return; }
        const desc = els.newProjectDesc.value.trim();
        try {
            showLoading(true);
            const res = await fetch(`${API_BASE}/projects`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, description: desc }),
            });
            if (!res.ok) throw new Error('Create failed');
            const data = await res.json();
            showToast('Project created', 'success');
            els.newProjectName.value = '';
            els.newProjectDesc.value = '';
            els.newProjectForm.classList.add('hidden');
            currentProjectId = data.id;
            await reloadAll();
            await openManageModal();
        } catch (e) {
            showToast('Failed to create project', 'error');
        } finally {
            showLoading(false);
        }
    }

    // ── Park modal ─────────────────────────────────────────────────────
    async function openParkModal() {
        try {
            const cur = await fetchJSON(`${API_BASE}/projects/current`);
            els.parkProjectName.textContent = cur.name || 'this project';
            const metrics = await fetchJSON(`${API_BASE}/metrics`);
            const tasks = await fetchJSON(`${API_BASE}/tasks`);
            const workers = await fetchJSON(`${API_BASE}/workers`);
            const inProgress = tasks.filter(t => t.status !== 'Plan' && t.status !== 'Done');
            const done = tasks.filter(t => t.status === 'Done');
            const busy = workers.filter(w => w.status === 'busy');
            const pct = metrics.progressPercentage || '0%';
            let html = `<p>${done.length} done, ${inProgress.length} in progress (${pct} complete)</p>`;
            if (busy.length) {
                html += '<ul>' + busy.map(w =>
                    `<li>${escHtml(w.name)} on "${escHtml(w.task_title || '?')}"</li>`
                ).join('') + '</ul>';
            }
            els.parkPreview.innerHTML = html;
            els.parkModal.classList.remove('hidden');
        } catch (err) {
            showToast('Failed to prepare park', 'error');
        }
    }

    function closeParkModal() {
        els.parkModal.classList.add('hidden');
        els.parkNote.value = '';
    }

    async function confirmPark() {
        const note = els.parkNote.value.trim();
        try {
            showLoading(true);
            await fetch(`${API_BASE}/projects/${currentProjectId}/park`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ park_note: note }),
            });
            showToast('Project parked', 'success');
            closeParkModal();
            await reloadAll();
        } catch (err) {
            showToast('Failed to park', 'error');
        } finally {
            showLoading(false);
        }
    }

    // ── Retry (US-27) ───────────────────────────────────────────────────
    window.handleRetry = async function(id) {
        if (!confirm('Retry task #' + id + '?')) return;
        try {
            showLoading(true);
            const res = await fetch(`${API_BASE}/tasks/${id}/retry`, { method: 'POST' });
            if (!res.ok) throw new Error('Retry failed');
            showToast('Task retry initiated', 'success');
            await fetchData();
        } catch (err) {
            showToast('Failed to retry task', 'error');
        } finally {
            showLoading(false);
        }
    };

    // ── Event listeners ────────────────────────────────────────────────
    els.btnRefresh.addEventListener('click', reloadAll);
    els.btnNewTask.addEventListener('click', () => openModal('New Task', null));

    els.btnStartWorkers.addEventListener('click', async () => {
        els.btnStartWorkers.disabled = true;
        try {
            const res = await fetch(`${API_BASE}/workers/start`, { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                const d = data.dispatched || [];
                if (d.length > 0) {
                    const names = d.map(t => `${t.worker_name}→${t.title}`).join(', ');
                    showToast(`Started ${d.length} tasks: ${names}`, 'success');
                } else {
                    showToast('All workers ready — no tasks to dispatch', 'info');
                }
                await reloadAll();
            }
        } catch (err) {
            showToast('Failed to start workers', 'error');
        } finally {
            els.btnStartWorkers.disabled = false;
        }
    });

    els.btnStopWorkers.addEventListener('click', async () => {
        els.btnStopWorkers.disabled = true;
        try {
            const res = await fetch(`${API_BASE}/workers/stop`, { method: 'POST' });
            const data = await res.json();
            if (data.success) {
                showToast(`Stopped ${data.updated} worker${data.updated !== 1 ? 's' : ''}`, 'success');
                await reloadAll();
            }
        } catch (err) {
            showToast('Failed to stop workers', 'error');
        } finally {
            els.btnStopWorkers.disabled = false;
        }
    });

    els.modalClose.addEventListener('click', closeModal);
    els.formCancel.addEventListener('click', closeModal);
    els.modal.addEventListener('click', (e) => {
        if (e.target === els.modal) closeModal();
    });

    els.taskForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = els.taskId.value;
        const isUpdate = id !== '';
        const payload = {
            title: els.taskTitle.value.trim(),
            description: els.taskDescription.value.trim(),
            status: els.taskStatus.value,
        };
        const agentId = els.taskAssignedTo ? els.taskAssignedTo.value : '';
        if (agentId) payload.assigned_to = parseInt(agentId, 10);
        // Include depends_on
        const depSel = els.taskDependsOn;
        if (depSel) {
            const deps = Array.from(depSel.selectedOptions).map(opt => parseInt(opt.value));
            payload.depends_on = deps.length > 0 ? JSON.stringify(deps) : null;
        }
        // Include new fields
        if (els.taskComplexity) payload.complexity = parseInt(els.taskComplexity.value, 10) || null;
        if (els.taskStoryUrl) payload.story_url = els.taskStoryUrl.value.trim() || null;
        if (els.taskStoryId) payload.story_id = parseInt(els.taskStoryId.value, 10) || null;

        if (!payload.title) {
            showToast('Title is required', 'error');
            return;
        }
        // Guard: must have a project
        if (!currentProjectId) {
            showToast('No project selected — wait for data to load', 'error');
            return;
        }
        // Include current project_id
        if (currentProjectId) payload.project_id = currentProjectId;
        els.formSubmit.disabled = true;
        try {
            const url = isUpdate ? `${API_BASE}/tasks/${id}` : `${API_BASE}/tasks`;
            const method = isUpdate ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (!res.ok) throw new Error('Save failed');
            showToast(isUpdate ? 'Task updated' : 'Task created', 'success');
            closeModal();
            await fetchData();
        } catch (err) {
            showToast('Failed to save task', 'error');
        } finally {
            els.formSubmit.disabled = false;
        }
    });

    // Project dropdown
    els.projectDropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleProjectDropdown();
    });

    // Sortable headers
    document.querySelectorAll('#task-table .sortable').forEach(th => {
        th.addEventListener('click', () => handleSort(th.dataset.sort));
    });

    // Created-by filter pills
    document.querySelectorAll('#created-by-filter .filter-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#created-by-filter .filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            createdByFilter = btn.dataset.filter;
            renderTasks(allTasks);
        });
    });

    document.addEventListener('click', (e) => {
        if (!els.projectDropdown.classList.contains('hidden') &&
            !els.projectDropdown.contains(e.target) &&
            e.target !== els.projectDropdownBtn &&
            !els.projectDropdownBtn.contains(e.target)) {
            closeProjectDropdown();
        }
    });

    els.manageProjectsBtn.addEventListener('click', () => {
        closeProjectDropdown();
        openManageModal();
    });

    els.btnPark.addEventListener('click', openParkModal);

    // Park modal
    els.parkConfirm.addEventListener('click', confirmPark);
    els.parkCancel.addEventListener('click', closeParkModal);
    els.parkModalClose.addEventListener('click', closeParkModal);
    els.parkModal.addEventListener('click', (e) => {
        if (e.target === els.parkModal) closeParkModal();
    });

    // Manage modal
    els.manageModalClose.addEventListener('click', () => {
        els.manageModal.classList.add('hidden');
    });
    els.manageModal.addEventListener('click', (e) => {
        if (e.target === els.manageModal) els.manageModal.classList.add('hidden');
    });
    els.btnNewProject.addEventListener('click', () => {
        els.newProjectForm.classList.toggle('hidden');
        if (!els.newProjectForm.classList.contains('hidden')) {
            els.newProjectName.focus();
        }
    });
    els.createProjectSubmit.addEventListener('click', createProject);
    els.createProjectCancel.addEventListener('click', () => {
        els.newProjectForm.classList.add('hidden');
        els.newProjectName.value = '';
        els.newProjectDesc.value = '';
    });
    els.newProjectName.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') createProject();
        if (e.key === 'Escape') {
            els.newProjectForm.classList.add('hidden');
            els.newProjectName.value = '';
            els.newProjectDesc.value = '';
        }
    });

    // Agents modal (US-22)
    els.manageAgentsBtn.addEventListener('click', openAgentsModal);
    els.agentsModalClose.addEventListener('click', closeAgentsModal);
    els.agentsModal.addEventListener('click', (e) => {
        if (e.target === els.agentsModal) closeAgentsModal();
    });
    els.btnNewAgent.addEventListener('click', () => {
        els.newAgentForm.classList.toggle('hidden');
        if (!els.newAgentForm.classList.contains('hidden')) {
            els.newAgentName.focus();
        }
    });
    els.createAgentSubmit.addEventListener('click', createAgent);
    els.createAgentCancel.addEventListener('click', () => {
        els.newAgentForm.classList.add('hidden');
        els.newAgentName.value = '';
        els.newAgentRole.value = '';
        els.newAgentModel.value = '';
        els.newAgentProvider.value = '';
        els.newAgentToolset.value = '';
    });
    els.newAgentName.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') createAgent();
        if (e.key === 'Escape') {
            els.newAgentForm.classList.add('hidden');
        }
    });

    // Swarm refresh
    if (els.swarmRefreshBtn) {
        els.swarmRefreshBtn.addEventListener('click', () => {
            fetchData();
        });
    }

    // ── DB Status Indicator ───────────────────────────────────────────
    const statusDot = $('status-dot');
    const statusText = $('status-text');

    async function checkDbStatus() {
        try {
            const res = await fetch(`${API_BASE}/status`);
            const data = await res.json();
            if (data.db === 'connected') {
                statusDot.className = 'status-dot connected';
                statusText.textContent = 'Connected';
            } else {
                statusDot.className = 'status-dot degraded';
                statusText.textContent = 'DB degraded';
            }
        } catch {
            statusDot.className = 'status-dot disconnected';
            statusText.textContent = 'Disconnected';
        }
    }

    // ── Collapse / Expand sections ─────────────────────────────────────
    function initCollapse() {
        const sections = document.querySelectorAll('[data-collapsible]');
        sections.forEach(section => {
            const id = section.dataset.collapsible;
            const toggle = section.querySelector('.collapsible-toggle');
            if (!toggle) return;
            // Restore saved state
            const saved = localStorage.getItem('vibeboard_collapse_' + id);
            if (saved === 'collapsed') section.classList.add('collapsed');
            toggle.addEventListener('click', () => {
                section.classList.toggle('collapsed');
                localStorage.setItem('vibeboard_collapse_' + id,
                    section.classList.contains('collapsed') ? 'collapsed' : '');
            });
        });
    }

    // ── Init ────────────────────────────────────────────────────────────
    // New Story button
    if (els.storyModal && els.storyForm && els.btnNewStory) {
        // Open modal
        els.btnNewStory.addEventListener('click', () => {
            els.storyForm.reset();
            els.storyId ? els.storyId.value = '' : null;
            els.storyModal.classList.remove('hidden');
            els.storyTitle.focus();
        });
        // Close handlers
        const closeStory = () => els.storyModal.classList.add('hidden');
        els.storyCancel && els.storyCancel.addEventListener('click', closeStory);
        els.storyClose && els.storyClose.addEventListener('click', closeStory);
        els.storyModal.addEventListener('click', (e) => {
            if (e.target === els.storyModal) closeStory();
        });
        // Form submit
        els.storyForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const payload = {
                title: els.storyTitle.value.trim(),
                description: els.storyDescription.value.trim(),
                story_type: els.storyType.value,
                complexity: parseInt(els.storyComplexity.value) || 3,
            };
            if (!payload.title) return;
            fetch(API_BASE + '/stories', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }).then(r => r.json()).then(() => {
                showToast('Story created', 'success');
                closeStory();
                fetchData();
            }).catch(() => showToast('Failed to create story', 'error'));
        });
    }
    // GitHub Integration button
    if (els.btnGhIntegrate) {
        els.btnGhIntegrate.addEventListener('click', () => {
            const repoUrl = prompt('Enter GitHub repository URL (e.g. https://github.com/user/repo):');
            if (!repoUrl || !repoUrl.trim()) return;
            // Fetch issues from the repo via the backend
            fetch(API_BASE + '/stories/import?repo=' + encodeURIComponent(repoUrl.trim()))
                .then(r => r.json())
                .then(data => {
                    if (data.imported > 0) {
                        showToast('Imported ' + data.imported + ' issue' + (data.imported === 1 ? '' : 's'), 'success');
                    } else if (data.message) {
                        showToast(data.message, 'info');
                    }
                    fetchData();
                })
                .catch(() => showToast('Failed to import GitHub issues', 'error'));
        });
    }
    fetchData();
    checkDbStatus();
    initDragDrop();
    initFrameSelect();
    initCollapse();

    // Auto-refresh every 30s (full dashboard)
    setInterval(fetchData, 30000);
    setInterval(checkDbStatus, 15000);

    // Fast worker status polling (US-14)
    startWorkerPolling();

})();
