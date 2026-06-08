(function () {
    const state = window.managementData || { students: [], teachers: [], courses: [], summary: {} };
    const config = window.managementConfig || {};
    const apiUrl = config.apiUrl || '../../Backend/management_api.php';
    let toastTimer = null;

    const dom = {
        moduleButtons: Array.from(document.querySelectorAll('[data-module-switch]')),
        sections: Array.from(document.querySelectorAll('[data-module]')),
        searchInputs: Array.from(document.querySelectorAll('[data-search-target]')),
        filterSelects: Array.from(document.querySelectorAll('[data-filter-target]')),
        tableBodies: {
            students: document.querySelector('[data-table-body="students"]'),
            teachers: document.querySelector('[data-table-body="teachers"]'),
            courses: document.querySelector('[data-table-body="courses"]'),
        },
        emptyStates: {
            students: document.querySelector('[data-empty-state="students"]'),
            teachers: document.querySelector('[data-empty-state="teachers"]'),
            courses: document.querySelector('[data-empty-state="courses"]'),
        },
        detail: {
            students: {
                container: document.getElementById('studentProfileContainer'),
                avatar: document.getElementById('studentHeroAvatar'),
                title: document.getElementById('studentHeroName'),
                status: document.getElementById('studentHeroStatus'),
                course: document.getElementById('studentHeroCourse'),
                semester: document.getElementById('studentHeroSemester'),
                id: document.getElementById('studentHeroId'),
                fullName: document.getElementById('studentDetailFullName'),
                gender: document.getElementById('studentDetailGender'),
                dob: document.getElementById('studentDetailDob'),
                phone: document.getElementById('studentDetailPhone'),
                email: document.getElementById('studentDetailEmail'),
                address: document.getElementById('studentDetailAddress'),
                courseName: document.getElementById('studentDetailCourseName'),
                courseCode: document.getElementById('studentDetailCourseCode'),
                semesterVal: document.getElementById('studentDetailSemester'),
                present: document.getElementById('studentDetailPresent'),
                absent: document.getElementById('studentDetailAbsent'),
                leave: document.getElementById('studentDetailLeave'),
                feeDue: document.getElementById('studentDetailFeeDue'),
                feePaid: document.getElementById('studentDetailFeePaid'),
                feeStatus: document.getElementById('studentDetailFeeStatus'),
                credentialEmail: document.getElementById('studentProfileEmail'),
                credentialPassword: document.getElementById('studentProfilePassword'),
            },
            teachers: {
                container: document.getElementById('teacherProfileContainer'),
                avatar: document.getElementById('teacherHeroAvatar'),
                title: document.getElementById('teacherHeroName'),
                status: document.getElementById('teacherHeroStatus'),
                department: document.getElementById('teacherHeroDepartment'),
                id: document.getElementById('teacherHeroId'),
                fullName: document.getElementById('teacherDetailFullName'),
                phone: document.getElementById('teacherDetailPhone'),
                email: document.getElementById('teacherDetailEmail'),
                departmentVal: document.getElementById('teacherDetailDepartment'),
                qualification: document.getElementById('teacherDetailQualification'),
                joiningDate: document.getElementById('teacherDetailJoiningDate'),
                assignedCourses: document.getElementById('teacherDetailAssignedCourses'),
                credentialEmail: document.getElementById('teacherProfileEmail'),
                credentialPassword: document.getElementById('teacherProfilePassword'),
            },
            courses: {
                placeholder: document.querySelector('[data-placeholder="courses"]'),
                content: document.querySelector('[data-detail-content="courses"]'),
                title: document.querySelector('[data-detail-title="courses"]'),
                subtitle: document.querySelector('[data-detail-subtitle="courses"]'),
                avatar: document.querySelector('[data-avatar="courses"]'),
                personal: document.querySelector('[data-detail-personal="courses"]'),
                course: document.querySelector('[data-detail-course="courses"]'),
                attendance: document.querySelector('[data-detail-attendance="courses"]'),
                fees: document.querySelector('[data-detail-fees="courses"]'),
                teachersList: document.querySelector('[data-course-teachers="courses"]'),
            },
        },
        modalBackdrop: document.querySelector('[data-modal-backdrop]'),
        modal: document.querySelector('[data-modal]'),
        modalTitle: document.querySelector('[data-modal-title]'),
        modalSubtitle: document.querySelector('[data-modal-subtitle]'),
        modalForm: document.querySelector('[data-modal-form]'),
        formFields: {
            students: document.querySelector('[data-form-fields="students"]'),
            teachers: document.querySelector('[data-form-fields="teachers"]'),
            courses: document.querySelector('[data-form-fields="courses"]'),
        },
        summaryValues: Array.from(document.querySelectorAll('[data-summary-value]')),
        summarySubtitles: Array.from(document.querySelectorAll('[data-summary-subtitle]')),
        toast: document.querySelector('[data-management-toast]'),
        saveButton: document.querySelector('[data-modal-form] .primary-button'),
    };

    const appState = {
        activeModule: 'students',
        selected: {
            students: null,
            teachers: null,
            courses: null,
        },
        filters: {
            students: { search: '', status: 'all' },
            teachers: { search: '', status: 'all' },
            courses: { search: '', status: 'all' },
        },
        modal: {
            module: null,
            mode: 'create',
            recordId: null,
        },
    };

    const formatCurrency = (value) => 'Rs. ' + new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0);
    const formatDate = (value) => {
        if (!value) {
            return '-';
        }

        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const initials = (value) => {
        const parts = String(value || '').trim().split(/\s+/).filter(Boolean);
        return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('') || '?';
    };

    const normalize = (value) => String(value ?? '').toLowerCase().trim();

    const normalizeModule = (module) => {
        if (module === 'student' || module === 'students') {
            return 'students';
        }

        if (module === 'teacher' || module === 'teachers') {
            return 'teachers';
        }

        return 'courses';
    };

    const showToast = (message, type = 'success') => {
        if (!dom.toast) {
            window.alert(message);
            return;
        }

        dom.toast.textContent = message;
        dom.toast.classList.remove('hidden', 'is-error', 'is-success');
        dom.toast.classList.add(type === 'error' ? 'is-error' : 'is-success');

        if (toastTimer) {
            window.clearTimeout(toastTimer);
        }

        toastTimer = window.setTimeout(() => {
            dom.toast.classList.add('hidden');
        }, 4200);
    };

    const apiRequest = async (module, action, payload = {}) => {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                module: normalizeModule(module),
                action,
                payload,
            }),
        });

        let result = {};
        try {
            result = await response.json();
        } catch (error) {
            throw new Error('Invalid server response.');
        }

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Request failed.');
        }

        return result;
    };

    const applySummary = (summary) => {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        state.summary = summary;
        setSummary();
    };

    const getFieldValue = (record, config) => {
        if (!record) {
            return config.defaultValue ?? '';
        }

        if (config.name === 'is_active') {
            return record.is_active ? '1' : '0';
        }

        if (config.name === 'course_id') {
            return record.course_id != null ? String(record.course_id) : '';
        }

        return record[config.name] ?? '';
    };

    const getCourseOptions = () => {
        const courses = getCollection('courses');

        if (!courses.length) {
            return [{ value: '', label: 'No courses available — create a course first' }];
        }

        return [
            { value: '', label: 'Select Course' },
            ...courses.map((course) => ({
                value: String(course.id),
                label: course.is_active ? course.course_name : `${course.course_name} (Inactive)`,
            })),
        ];
    };

    const generateUsername = (fullName, prefix = '') => {
        const base = normalize(fullName).replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || prefix || 'user';
        const suffix = Math.floor(100 + Math.random() * 900);
        return `${base}_${suffix}`;
    };

    const generatePassword = (length = 10) => {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        const bytes = new Uint32Array(length);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, (value) => chars[value % chars.length]).join('');
    };

    const getCollection = (module) => state[module] || [];

    const getStatusLabel = (active) => (active ? 'Active' : 'Inactive');

    const getStatusClass = (active) => (active ? 'status-badge status-active' : 'status-badge status-inactive');

    const getSelectedRecord = (module) => {
        const collection = getCollection(module);
        const selectedId = appState.selected[module];
        const found = collection.find((record) => Number(record.id) === Number(selectedId));
        return found || collection[0] || null;
    };

    const setSummary = () => {
        const summary = state.summary || {};
        dom.summaryValues.forEach((element) => {
            const key = element.getAttribute('data-summary-value');
            element.textContent = Number(summary[key] || 0).toString();
        });

        dom.summarySubtitles.forEach((element) => {
            const key = element.getAttribute('data-summary-subtitle');
            element.textContent = `${Number(summary[key] || 0)} active`;
        });
    };

    const setActiveModule = (module) => {
        appState.activeModule = module;
        dom.moduleButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-module-switch') === module));
        dom.sections.forEach((section) => section.classList.toggle('active', section.getAttribute('data-module') === module));

        // Update sidebar nav-item active class dynamically
        document.querySelectorAll('.sidebar .nav-menu .nav-item').forEach((item) => {
            const href = item.getAttribute('href');
            if (href === `#${module}` || href === `management.php#${module}`) {
                item.classList.add('active');
            } else if (href && (href.startsWith('#') || href.startsWith('management.php#'))) {
                item.classList.remove('active');
            }
        });

        // Reset student view back to list
        if (module === 'students') {
            const gridNode = document.getElementById('studentsGrid');
            const headerNode = document.getElementById('studentsHeader');
            const profileNode = document.getElementById('studentProfileContainer');
            if (gridNode) gridNode.classList.remove('hidden');
            if (headerNode) headerNode.classList.remove('hidden');
            if (profileNode) profileNode.classList.add('hidden');
        }

        // Reset teacher view back to list
        if (module === 'teachers') {
            const gridNode = document.getElementById('teachersGrid');
            const headerNode = document.getElementById('teachersHeader');
            const profileNode = document.getElementById('teacherProfileContainer');
            if (gridNode) gridNode.classList.remove('hidden');
            if (headerNode) headerNode.classList.remove('hidden');
            if (profileNode) profileNode.classList.add('hidden');
        }
    };

    const closeAllRowMenus = () => {
        document.querySelectorAll('[data-row-actions]').forEach((container) => {
            const panel = container.querySelector('.row-menu-panel');
            const toggle = container.querySelector('[data-row-menu-toggle]');

            if (panel) {
                panel.classList.add('hidden');
                panel.classList.remove('is-open');
                panel.style.position = '';
                panel.style.top = '';
                panel.style.left = '';
                panel.style.right = '';
                panel.style.visibility = '';
                panel.style.zIndex = '';
            }

            toggle?.setAttribute('aria-expanded', 'false');
        });
    };

    const positionRowMenu = (toggle, panel) => {
        panel.classList.remove('hidden');
        panel.style.position = 'fixed';
        panel.style.visibility = 'hidden';
        panel.style.top = '0';
        panel.style.left = '0';
        panel.style.zIndex = '2500';

        const panelWidth = panel.offsetWidth;
        const panelHeight = panel.offsetHeight;
        const rect = toggle.getBoundingClientRect();

        let top = rect.bottom + 8;
        let left = rect.right - panelWidth;
        left = Math.max(12, Math.min(left, window.innerWidth - panelWidth - 12));

        if (top + panelHeight > window.innerHeight - 12) {
            top = Math.max(12, rect.top - panelHeight - 8);
        }

        panel.style.top = `${top}px`;
        panel.style.left = `${left}px`;
        panel.style.right = 'auto';
        panel.style.visibility = '';
        panel.classList.add('is-open');
    };

    const buildRowActionsMenu = (module, record) => {
        const header = module === 'students'
            ? 'Student Settings'
            : module === 'teachers'
                ? 'Teacher Settings'
                : 'Course Settings';
        const toggleLabel = record.is_active ? 'Deactivate' : 'Activate';
        const items = module === 'courses'
            ? [
                { action: 'edit', label: 'Edit Course' },
                { action: 'toggle', label: toggleLabel },
                { action: 'delete', label: 'Delete Course', danger: true },
            ]
            : [
                { action: 'edit', label: 'Edit' },
                { action: 'credentials', label: 'View Credentials' },
                { action: 'toggle', label: toggleLabel },
                { action: 'delete', label: 'Delete', danger: true },
            ];

        return `
            <div class="row-actions" data-row-actions>
                <button
                    type="button"
                    class="action-button primary"
                    data-action="view"
                    data-module="${module}"
                    data-id="${record.id}"
                    style="min-height: 36px;"
                >View</button>
                <button
                    type="button"
                    class="row-menu-toggle"
                    data-row-menu-toggle
                    aria-label="Open ${header.toLowerCase()}"
                    aria-expanded="false"
                    aria-haspopup="true"
                >
                    <span class="row-menu-dots" aria-hidden="true"></span>
                </button>
                <div class="row-menu-panel hidden" role="menu">
                    <div class="row-menu-header">${header}</div>
                    ${items.map((item) => `
                        <button
                            type="button"
                            class="row-menu-item${item.danger ? ' danger' : ''}"
                            role="menuitem"
                            data-action="${item.action}"
                            data-module="${module}"
                            data-id="${record.id}"
                        >${item.label}</button>
                    `).join('')}
                </div>
            </div>
        `;
    };

    const buildTableRows = (module) => {
        const collection = getCollection(module);
        const searchValue = normalize(appState.filters[module].search);
        const statusValue = appState.filters[module].status;

        return collection.filter((record) => {
            const statusMatch = statusValue === 'all' || (record.is_active ? 'active' : 'inactive') === statusValue;
            const searchable = module === 'courses'
                ? [record.course_name, record.course_code, record.duration, record.description].join(' ')
                : module === 'teachers'
                    ? [record.full_name, record.username, record.email, record.department, record.qualification, record.phone].join(' ')
                    : [record.full_name, record.username, record.email, record.phone, record.address, record.course_name, record.course_code].join(' ');

            return statusMatch && normalize(searchable).includes(searchValue);
        });
    };

    const renderRows = (module) => {
        const tbody = dom.tableBodies[module];
        const emptyState = dom.emptyStates[module];
        const rows = buildTableRows(module);

        if (!tbody) {
            return;
        }

        tbody.innerHTML = '';

        if (!rows.length) {
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');

        rows.forEach((record) => {
            const row = document.createElement('tr');
            const recordLabel = module === 'courses' ? record.course_name : record.full_name;
            const photoMarkup = record.profile_photo
                ? `<div class="row-photo"><img src="${record.profile_photo}" alt="${recordLabel}"></div>`
                : `<div class="row-photo">${initials(recordLabel)}</div>`;

            if (module === 'students') {
                row.innerHTML = `
                    <td>#${record.id}</td>
                    <td>${photoMarkup}</td>
                    <td>${record.full_name}</td>
                    <td>${record.username}</td>
                    <td>${record.email}</td>
                    <td>${record.phone || '-'}</td>
                    <td>${record.course_name || '-'}</td>
                    <td><span class="${getStatusClass(record.is_active)}">${getStatusLabel(record.is_active)}</span></td>
                    <td class="actions-cell">${buildRowActionsMenu('students', record)}</td>
                `;
            }

            if (module === 'teachers') {
                row.innerHTML = `
                    <td>#${record.id}</td>
                    <td>${photoMarkup}</td>
                    <td>${record.full_name}</td>
                    <td>${record.username}</td>
                    <td>${record.email}</td>
                    <td>${record.department || '-'}</td>
                    <td><span class="${getStatusClass(record.is_active)}">${getStatusLabel(record.is_active)}</span></td>
                    <td class="actions-cell">${buildRowActionsMenu('teachers', record)}</td>
                `;
            }

            if (module === 'courses') {
                row.innerHTML = `
                    <td>#${record.id}</td>
                    <td>${record.course_name}</td>
                    <td>${record.course_code}</td>
                    <td>${record.duration}</td>
                    <td>${formatCurrency(record.total_fees)}</td>
                    <td>${record.total_students}</td>
                    <td><span class="${getStatusClass(record.is_active)}">${getStatusLabel(record.is_active)}</span></td>
                    <td class="actions-cell">${buildRowActionsMenu('courses', record)}</td>
                `;
            }

            tbody.appendChild(row);
        });

        if (!appState.selected[module] && rows.length) {
            appState.selected[module] = rows[0].id;
        }
    };

    const renderCredentialPassword = (module, record) => {
        const detail = dom.detail[module];
        if (!detail?.credentialPassword) {
            return;
        }

        const credentials = record.credentials || {};
        const password = String(credentials.password || '');
        const visible = Boolean(credentials.passwordVisible && password);

        detail.credentialPassword.textContent = visible ? password : '********';
        detail.credentialPassword.classList.toggle('is-visible', visible);

        const toggleButton = document.querySelector(`[data-toggle-password="${module}"]`);
        if (toggleButton) {
            toggleButton.textContent = visible ? 'Hide Password' : 'Show Password';
        }
    };

    const replaceRecordInState = (module, nextRecord) => {
        const collectionName = normalizeModule(module);
        const list = getCollection(collectionName);
        const index = list.findIndex((entry) => Number(entry.id) === Number(nextRecord.id));

        if (index >= 0) {
            list[index] = nextRecord;
        }

        state[collectionName] = list;
        appState.selected[collectionName] = nextRecord.id;
    };

    const renderDetail = (module) => {
        const record = getSelectedRecord(module);
        const detail = dom.detail[module];

        if (!detail) {
            return;
        }

        if (module === 'students') {
            if (!record) {
                detail.container.classList.add('hidden');
                return;
            }

            // Populate Avatar and Hero Section
            detail.avatar.textContent = initials(record.full_name);
            detail.title.textContent = record.full_name;
            detail.status.textContent = record.is_active ? 'Active' : 'Inactive';
            detail.status.className = `status-badge ${getStatusClass(record.is_active)}`;

            detail.course.textContent = record.course_name || 'No Course';
            detail.semester.textContent = record.semester || 'No Semester';
            detail.id.textContent = `Student ID: #${record.id}`;

            // Populate Personal Details Card
            detail.fullName.textContent = record.full_name;
            detail.gender.textContent = record.gender ? record.gender.charAt(0).toUpperCase() + record.gender.slice(1) : '-';
            detail.dob.textContent = formatDate(record.date_of_birth);
            detail.phone.textContent = record.phone || '-';
            detail.email.textContent = record.email || '-';
            detail.address.textContent = record.address || '-';

            // Populate Academic Details Card
            detail.courseName.textContent = record.course_name || '-';
            detail.courseCode.textContent = record.course_code || '-';
            detail.semesterVal.textContent = record.semester || '-';

            // Populate Attendance Stats Card
            detail.present.textContent = record.attendance?.present || 0;
            detail.absent.textContent = record.attendance?.absent || 0;
            detail.leave.textContent = record.attendance?.leave || 0;

            // Populate Fee Summary Card
            detail.feeDue.textContent = formatCurrency(record.fees?.due || 0);
            detail.feePaid.textContent = formatCurrency(record.fees?.paid || 0);

            const statusLabel = record.fees?.status || 'pending';
            detail.feeStatus.textContent = statusLabel.toUpperCase();
            detail.feeStatus.className = `status-badge fee-${statusLabel}`;

            // Populate Credentials Card
            detail.credentialEmail.textContent = record.credentials?.email || record.email || '-';

            // Render Password Mask
            renderCredentialPassword(module, record);
            return;
        }

        if (module === 'teachers') {
            if (!record) {
                detail.container.classList.add('hidden');
                return;
            }

            // Populate Avatar and Hero Section
            detail.avatar.textContent = initials(record.full_name);
            detail.title.textContent = record.full_name;
            detail.status.textContent = record.is_active ? 'Active' : 'Inactive';
            detail.status.className = `status-badge ${getStatusClass(record.is_active)}`;
            detail.department.textContent = record.department || 'No Department';
            detail.id.textContent = `Teacher ID: #${record.id}`;

            // Populate Personal Details Card
            detail.fullName.textContent = record.full_name;
            detail.phone.textContent = record.phone || '-';
            detail.email.textContent = record.email || '-';

            // Populate Professional Info Card
            detail.departmentVal.textContent = record.department || '-';
            detail.qualification.textContent = record.qualification || '-';
            detail.joiningDate.textContent = formatDate(record.joining_date);

            // Populate Assigned Courses Card
            detail.assignedCourses.textContent = record.assigned_courses || 0;

            // Populate Credentials Card
            detail.credentialEmail.textContent = record.credentials?.email || record.email || '-';

            // Render Password Mask
            renderCredentialPassword(module, record);
            return;
        }

        if (!record) {
            detail.placeholder.classList.remove('hidden');
            detail.content.classList.add('hidden');
            return;
        }

        detail.placeholder.classList.add('hidden');
        detail.content.classList.remove('hidden');

        if (module === 'courses') {
            detail.avatar.textContent = initials(record.course_name);
            detail.title.textContent = record.course_name;
            detail.subtitle.textContent = `${record.course_code} • ${record.duration}`;
            detail.personal.textContent = `Semester Count: ${record.semester_count || 0} | Description: ${record.description || '-'}`;
            detail.course.textContent = `Assigned Students: ${record.total_students || 0}`;
            detail.attendance.textContent = `Assigned Teachers: ${record.total_teachers || 0}`;
            detail.fees.textContent = formatCurrency(record.total_fees || 0);
            detail.teachersList.innerHTML = '';

            if (Array.isArray(record.assigned_teachers) && record.assigned_teachers.length) {
                detail.teachersList.innerHTML = record.assigned_teachers.map((teacher) => `
                    <article class="assigned-item">
                        <strong>${teacher.full_name}</strong>
                        <span>${teacher.username} • ${teacher.email}</span>
                    </article>
                `).join('');
            } else {
                detail.teachersList.innerHTML = '<div class="assigned-item"><strong>No assigned teachers</strong><span>Assign teachers from the backend when available.</span></div>';
            }
        }
    };

    const renderModule = (module) => {
        renderRows(module);
        renderDetail(module);
    };

    const renderAll = () => {
        setSummary();
        ['students', 'teachers', 'courses'].forEach((module) => renderModule(module));
    };

    const setSearch = (module, value) => {
        appState.filters[module].search = value;
        renderModule(module);
    };

    const setFilter = (module, value) => {
        appState.filters[module].status = value;
        renderModule(module);
    };

    const openModal = (module, mode = 'create', recordId = null) => {
        appState.modal.module = normalizeModule(module);
        appState.modal.mode = mode;
        appState.modal.recordId = recordId;

        const titleMap = {
            students: 'Student Form',
            teachers: 'Teacher Form',
            courses: 'Course Form',
        };

        const subtitleMap = {
            students: 'Create or update student accounts and automatic credentials.',
            teachers: 'Create or update teacher accounts and automatic credentials.',
            courses: 'Create or update course records ready for assignment.',
        };

        dom.modalTitle.textContent = `${mode === 'edit' ? 'Edit' : 'Add'} ${titleMap[module].replace(' Form', '')}`;
        dom.modalSubtitle.textContent = subtitleMap[module];
        dom.modal.classList.remove('hidden');
        dom.modalBackdrop.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        renderForm(normalizeModule(module), getRecordForForm(normalizeModule(module), recordId));
    };

    const closeModal = () => {
        dom.modal.classList.add('hidden');
        dom.modalBackdrop.classList.add('hidden');
        document.body.style.overflow = '';
        dom.modalForm.reset();
    };

    const getRecordForForm = (module, recordId) => {
        if (!recordId) {
            return null;
        }

        return getCollection(module).find((record) => Number(record.id) === Number(recordId)) || null;
    };

    const renderForm = (module, record) => {
        const containers = dom.formFields;
        Object.entries(containers).forEach(([key, element]) => {
            element.classList.toggle('hidden', key !== module);
            element.innerHTML = '';
        });

        const createField = (config) => {
            const field = document.createElement('div');
            field.className = `form-field${config.fullWidth ? ' full-width' : ''}`;
            field.innerHTML = `<label for="${config.name}">${config.label}</label>`;

            let control = '';
            const value = getFieldValue(record, config);

            if (config.type === 'textarea') {
                control = `<textarea id="${config.name}" name="${config.name}" placeholder="${config.placeholder || ''}">${value || ''}</textarea>`;
            } else if (config.type === 'select') {
                const options = typeof config.options === 'function' ? config.options() : config.options || [];
                control = `<select id="${config.name}" name="${config.name}">${options.map((option) => `<option value="${option.value}" ${String(option.value) === String(value) ? 'selected' : ''}>${option.label}</option>`).join('')}</select>`;
            } else {
                control = `<input id="${config.name}" type="${config.type}" name="${config.name}" value="${value || ''}" placeholder="${config.placeholder || ''}">`;
            }

            field.insertAdjacentHTML('beforeend', control);
            return field;
        };

        const studentFields = [
            { name: 'full_name', label: 'Full Name', type: 'text', placeholder: 'Enter full name' },
            { name: 'username', label: 'Username', type: 'text', placeholder: 'Auto generated if blank' },
            { name: 'email', label: 'Email', type: 'email', placeholder: 'student@example.com' },
            { name: 'phone', label: 'Phone', type: 'text', placeholder: 'Phone number' },
            { name: 'address', label: 'Address', type: 'textarea', fullWidth: true, placeholder: 'Student address' },
            { name: 'gender', label: 'Gender', type: 'select', options: [
                { value: 'male', label: 'Male' },
                { value: 'female', label: 'Female' },
                { value: 'other', label: 'Other' },
            ] },
            { name: 'date_of_birth', label: 'Date of Birth', type: 'date' },
            { name: 'course_id', label: 'Course', type: 'select', options: getCourseOptions },
            { name: 'semester', label: 'Semester', type: 'text', placeholder: 'e.g. Semester 1' },
            { name: 'profile_photo', label: 'Profile Photo URL', type: 'url', fullWidth: true, placeholder: 'https://...' },
            { name: 'is_active', label: 'Status', type: 'select', options: [
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
            ] },
        ];

        const teacherFields = [
            { name: 'full_name', label: 'Full Name', type: 'text', placeholder: 'Enter full name' },
            { name: 'username', label: 'Username', type: 'text', placeholder: 'Auto generated if blank' },
            { name: 'email', label: 'Email', type: 'email', placeholder: 'teacher@example.com' },
            { name: 'phone', label: 'Phone', type: 'text', placeholder: 'Phone number' },
            { name: 'department', label: 'Department', type: 'text', placeholder: 'Department' },
            { name: 'qualification', label: 'Qualification', type: 'text', placeholder: 'Qualification' },
            { name: 'joining_date', label: 'Joining Date', type: 'date' },
            { name: 'profile_photo', label: 'Profile Photo URL', type: 'url', fullWidth: true, placeholder: 'https://...' },
            { name: 'is_active', label: 'Status', type: 'select', options: [
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
            ] },
        ];

        const courseFields = [
            { name: 'course_name', label: 'Course Name', type: 'text', placeholder: 'Course name' },
            { name: 'course_code', label: 'Course Code', type: 'text', placeholder: 'COURSE-101' },
            { name: 'duration', label: 'Duration', type: 'text', placeholder: 'e.g. 4 Years' },
            { name: 'semester_count', label: 'Semester Count', type: 'number', placeholder: '8' },
            { name: 'total_fees', label: 'Total Fees', type: 'number', placeholder: '0' },
            { name: 'description', label: 'Description', type: 'textarea', fullWidth: true, placeholder: 'Course description' },
            { name: 'is_active', label: 'Status', type: 'select', options: [
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
            ] },
        ];

        const selectedFields = module === 'students' ? studentFields : module === 'teachers' ? teacherFields : courseFields;
        selectedFields.forEach((field) => containers[module].appendChild(createField(field)));
    };

    const buildPayloadFromForm = (module, formData, recordId) => {
        const collectionName = normalizeModule(module);
        const payload = {};

        formData.forEach((value, key) => {
            payload[key] = typeof value === 'string' ? value.trim() : value;
        });

        if (collectionName === 'students') {
            if (!payload.full_name || !payload.email) {
                throw new Error('Full name and email are required.');
            }

            payload.is_active = String(payload.is_active || '1') === '1';
            payload.course_id = payload.course_id ? Number(payload.course_id) : null;
        }

        if (collectionName === 'teachers') {
            if (!payload.full_name || !payload.email || !payload.department) {
                throw new Error('Full name, email, and department are required.');
            }

            payload.is_active = String(payload.is_active || '1') === '1';
        }

        if (collectionName === 'courses') {
            if (!payload.course_name || !payload.course_code || !payload.duration) {
                throw new Error('Course name, code, and duration are required.');
            }

            payload.semester_count = Number(payload.semester_count || 1);
            payload.total_fees = Number(payload.total_fees || 0);
            payload.is_active = String(payload.is_active || '1') === '1';
        }

        if (recordId) {
            payload.id = Number(recordId);
        }

        return payload;
    };

    const saveRecord = async () => {
        const module = normalizeModule(appState.modal.module);
        const formData = new FormData(dom.modalForm);
        const recordId = appState.modal.recordId;
        const isEdit = appState.modal.mode === 'edit' && recordId;
        const saveButton = dom.saveButton;

        let payload;

        try {
            payload = buildPayloadFromForm(module, formData, recordId);
        } catch (error) {
            showToast(error.message || 'Please complete the required fields.', 'error');
            return;
        }

        if (saveButton) {
            saveButton.disabled = true;
            saveButton.textContent = isEdit ? 'Saving...' : 'Creating...';
        }

        try {
            const result = await apiRequest(module, isEdit ? 'update' : 'create', payload);
            upsertRecord(module, result.record);
            applySummary(result.summary);
            closeModal();
            showToast(result.message || 'Record saved successfully.');
        } catch (error) {
            showToast(error.message || 'Could not save the record.', 'error');
        } finally {
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.textContent = 'Save Record';
            }
        }
    };

    const upsertRecord = (collection, nextRecord) => {
        const list = getCollection(collection);
        const index = list.findIndex((record) => Number(record.id) === Number(nextRecord.id));

        if (index >= 0) {
            list[index] = nextRecord;
        } else {
            list.unshift(nextRecord);
        }

        state[collection] = list;
        appState.selected[collection] = nextRecord.id;

        if (collection === 'courses') {
            state.students = state.students.map((student) => student.course_id && Number(student.course_id) === Number(nextRecord.id)
                ? { ...student, course_name: nextRecord.course_name, course_code: nextRecord.course_code }
                : student);
        }

        setSummary();
        ['students', 'teachers', 'courses'].forEach((module) => renderModule(module));
    };

    const removeRecord = async (module, id) => {
        const collectionName = normalizeModule(module);
        const confirmed = window.confirm(`Delete this ${collectionName.slice(0, -1)}?`);
        if (!confirmed) {
            return;
        }

        try {
            const result = await apiRequest(collectionName, 'delete', { id: Number(id) });
            state[collectionName] = getCollection(collectionName).filter((record) => Number(record.id) !== Number(id));
            appState.selected[collectionName] = state[collectionName][0]?.id || null;
            applySummary(result.summary);
            renderAll();
            showToast(result.message || 'Record deleted successfully.');
        } catch (error) {
            showToast(error.message || 'Could not delete the record.', 'error');
        }
    };

    const toggleStatus = async (module, id) => {
        const collectionName = normalizeModule(module);

        try {
            const result = await apiRequest(collectionName, 'toggle', { id: Number(id) });
            const index = getCollection(collectionName).findIndex((record) => Number(record.id) === Number(id));
            if (index >= 0 && result.record) {
                getCollection(collectionName)[index] = result.record;
            }
            applySummary(result.summary);
            renderAll();
            showToast(result.message || 'Status updated successfully.');
        } catch (error) {
            showToast(error.message || 'Could not update the status.', 'error');
        }
    };

    const showRecord = (module, id) => {
        appState.selected[module] = Number(id);

        if (module === 'students') {
            renderDetail(module);
            appState.activeModule = 'students';
            dom.moduleButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-module-switch') === 'students'));
            dom.sections.forEach((section) => section.classList.toggle('active', section.getAttribute('data-module') === 'students'));

            document.getElementById('studentsGrid').classList.add('hidden');
            document.getElementById('studentsHeader').classList.add('hidden');
            document.getElementById('studentProfileContainer').classList.remove('hidden');
        } else if (module === 'teachers') {
            renderDetail(module);
            appState.activeModule = 'teachers';
            dom.moduleButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-module-switch') === 'teachers'));
            dom.sections.forEach((section) => section.classList.toggle('active', section.getAttribute('data-module') === 'teachers'));

            document.getElementById('teachersGrid').classList.add('hidden');
            document.getElementById('teachersHeader').classList.add('hidden');
            document.getElementById('teacherProfileContainer').classList.remove('hidden');
        } else {
            renderDetail(module);
            setActiveModule(module);
        }
    };

    const openCredentials = (module, id) => {
        const record = getCollection(module).find((entry) => Number(entry.id) === Number(id));
        if (!record) {
            return;
        }

        appState.selected[module] = Number(id);
        if (!record.credentials) {
            record.credentials = { email: record.email || '', password: '', passwordVisible: false };
        }

        renderDetail(module);

        if (module === 'students') {
            appState.activeModule = 'students';
            dom.moduleButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-module-switch') === 'students'));
            dom.sections.forEach((section) => section.classList.toggle('active', section.getAttribute('data-module') === 'students'));

            document.getElementById('studentsGrid').classList.add('hidden');
            document.getElementById('studentsHeader').classList.add('hidden');
            document.getElementById('studentProfileContainer').classList.remove('hidden');
        } else if (module === 'teachers') {
            appState.activeModule = 'teachers';
            dom.moduleButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-module-switch') === 'teachers'));
            dom.sections.forEach((section) => section.classList.toggle('active', section.getAttribute('data-module') === 'teachers'));

            document.getElementById('teachersGrid').classList.add('hidden');
            document.getElementById('teachersHeader').classList.add('hidden');
            document.getElementById('teacherProfileContainer').classList.remove('hidden');
        }
    };

    const togglePasswordVisibility = (module) => {
        const record = getSelectedRecord(module);
        if (!record) {
            return;
        }

        record.credentials = record.credentials || { email: record.email || '', password: '', passwordVisible: false };

        if (!record.credentials.password) {
            showToast('Password is encrypted. Click Reset Password first to generate a new login password.', 'error');
            return;
        }

        record.credentials.passwordVisible = !record.credentials.passwordVisible;
        renderDetail(module);
    };

    const resetPassword = async (module) => {
        const collectionName = normalizeModule(module);
        const record = getSelectedRecord(collectionName);
        if (!record) {
            return;
        }

        const confirmed = window.confirm('Generate a new password for this user? The old password will stop working.');
        if (!confirmed) {
            return;
        }

        try {
            const result = await apiRequest(collectionName, 'reset_password', { id: record.id });
            replaceRecordInState(collectionName, result.record);
            renderDetail(collectionName);
            showToast(result.message || 'Password reset successfully.');
        } catch (error) {
            showToast(error.message || 'Could not reset the password.', 'error');
        }
    };

    const copyValue = async (text) => {
        if (!text) {
            return;
        }

        try {
            await navigator.clipboard.writeText(text);
        } catch (error) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
        }
    };

    const buildSearchAndFilterHandlers = () => {
        dom.searchInputs.forEach((input) => {
            input.addEventListener('input', (event) => {
                const module = input.getAttribute('data-search-target');
                setSearch(module, event.target.value);
            });
        });

        dom.filterSelects.forEach((select) => {
            select.addEventListener('change', (event) => {
                const module = select.getAttribute('data-filter-target');
                setFilter(module, event.target.value);
            });
        });
    };

    const bindModuleSwitching = () => {
        dom.moduleButtons.forEach((button) => {
            button.addEventListener('click', () => setActiveModule(button.getAttribute('data-module-switch')));
        });
    };

    const bindSidebar = () => {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.querySelector('.backdrop');
        const toggles = document.querySelectorAll('[data-toggle-sidebar="true"]');
        const closers = document.querySelectorAll('[data-close-sidebar="true"]');

        const setSidebar = (open) => {
            body.dataset.sidebarOpen = String(open);
            sidebar.classList.toggle('is-open', open);
            backdrop.hidden = !open;
        };

        toggles.forEach((button) => button.addEventListener('click', () => setSidebar(true)));
        closers.forEach((button) => button.addEventListener('click', () => setSidebar(body.dataset.sidebarOpen !== 'true')));
        backdrop.addEventListener('click', () => setSidebar(false));
    };

    const bindModal = () => {
        document.querySelectorAll('[data-open-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const label = button.getAttribute('data-open-modal');
                const module = label === 'student' ? 'students' : label === 'teacher' ? 'teachers' : 'courses';
                appState.modal.mode = 'create';
                appState.modal.recordId = null;
                openModal(module, 'create', null);
            });
        });

        document.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', closeModal);
        });

        dom.modalBackdrop.addEventListener('click', closeModal);

        dom.modalForm.addEventListener('submit', (event) => {
            event.preventDefault();
            void saveRecord();
        });
    };

    const bindTableActions = () => {
        window.addEventListener('scroll', closeAllRowMenus, true);
        window.addEventListener('resize', closeAllRowMenus);

        document.addEventListener('click', (event) => {
            const menuToggle = event.target.closest('[data-row-menu-toggle]');
            if (menuToggle) {
                event.stopPropagation();
                const container = menuToggle.closest('[data-row-actions]');
                const panel = container?.querySelector('.row-menu-panel');
                const isOpen = panel && !panel.classList.contains('hidden');
                closeAllRowMenus();
                if (panel && !isOpen) {
                    positionRowMenu(menuToggle, panel);
                    menuToggle.setAttribute('aria-expanded', 'true');
                }
                return;
            }

            if (!event.target.closest('[data-row-actions]')) {
                closeAllRowMenus();
            }

            const button = event.target.closest('[data-action]');
            if (!button) {
                return;
            }

            closeAllRowMenus();

            const action = button.getAttribute('data-action');
            const module = button.getAttribute('data-module');
            const id = button.getAttribute('data-id');

            if (action === 'view') {
                showRecord(module, id);
                return;
            }

            if (action === 'edit') {
                appState.modal.mode = 'edit';
                appState.modal.recordId = Number(id);
                openModal(module, 'edit', Number(id));
                return;
            }

            if (action === 'delete') {
                void removeRecord(module, id);
                return;
            }

            if (action === 'toggle') {
                void toggleStatus(module, id);
                return;
            }

            if (action === 'credentials') {
                openCredentials(module, id);
            }
        });
    };

    const bindCredentialButtons = () => {
        document.addEventListener('click', async (event) => {
            const target = event.target;

            const copyButton = target.closest('[data-copy-email], [data-copy-password]');
            if (copyButton) {
                const module = copyButton.getAttribute('data-copy-email') || copyButton.getAttribute('data-copy-password');
                const record = getSelectedRecord(module);
                if (!record) {
                    return;
                }

                const value = copyButton.hasAttribute('data-copy-email')
                    ? (record.credentials?.email || record.email || '')
                    : (record.credentials?.password || '');
                await copyValue(value);
                return;
            }

            const toggleButton = target.closest('[data-toggle-password]');
            if (toggleButton) {
                togglePasswordVisibility(toggleButton.getAttribute('data-toggle-password'));
                return;
            }

            const resetButton = target.closest('[data-reset-password]');
            if (resetButton) {
                void resetPassword(resetButton.getAttribute('data-reset-password'));
            }
        });
    };

    const initializeForms = () => {
        renderForm('students', null);
        renderForm('teachers', null);
        renderForm('courses', null);
    };

    const initialize = () => {
        bindSidebar();
        bindModuleSwitching();
        buildSearchAndFilterHandlers();
        bindModal();
        bindTableActions();
        bindCredentialButtons();
        initializeForms();

        const hash = window.location.hash;
        const initialModule = (hash === '#teachers' || hash === '#courses') ? hash.slice(1) : 'students';
        setActiveModule(initialModule);

        renderAll();

        const backBtn = document.getElementById('backToStudentsList');
        if (backBtn) {
            backBtn.addEventListener('click', () => {
                document.getElementById('studentsGrid').classList.remove('hidden');
                document.getElementById('studentsHeader').classList.remove('hidden');
                document.getElementById('studentProfileContainer').classList.add('hidden');
            });
        }

        const backTeachersBtn = document.getElementById('backToTeachersList');
        if (backTeachersBtn) {
            backTeachersBtn.addEventListener('click', () => {
                document.getElementById('teachersGrid').classList.remove('hidden');
                document.getElementById('teachersHeader').classList.remove('hidden');
                document.getElementById('teacherProfileContainer').classList.add('hidden');
            });
        }

        window.addEventListener('hashchange', () => {
            const currentHash = window.location.hash;
            if (currentHash === '#teachers' || currentHash === '#courses' || currentHash === '#students') {
                setActiveModule(currentHash.slice(1));
            }
        });
    };

    document.addEventListener('DOMContentLoaded', initialize);
})();