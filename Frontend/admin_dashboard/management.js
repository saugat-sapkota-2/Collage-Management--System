(function () {
    const state = window.managementData || { students: [], teachers: [], courses: [] };
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
                placeholder: document.querySelector('[data-placeholder="students"]'),
                content: document.querySelector('[data-detail-content="students"]'),
                title: document.querySelector('[data-detail-title="students"]'),
                subtitle: document.querySelector('[data-detail-subtitle="students"]'),
                avatar: document.querySelector('[data-avatar="students"]'),
                personal: document.querySelector('[data-detail-personal="students"]'),
                course: document.querySelector('[data-detail-course="students"]'),
                attendance: document.querySelector('[data-detail-attendance="students"]'),
                fees: document.querySelector('[data-detail-fees="students"]'),
                credentialUsername: document.querySelector('[data-credential-username="students"]'),
                credentialPassword: document.querySelector('[data-credential-password="students"]'),
            },
            teachers: {
                placeholder: document.querySelector('[data-placeholder="teachers"]'),
                content: document.querySelector('[data-detail-content="teachers"]'),
                title: document.querySelector('[data-detail-title="teachers"]'),
                subtitle: document.querySelector('[data-detail-subtitle="teachers"]'),
                avatar: document.querySelector('[data-avatar="teachers"]'),
                personal: document.querySelector('[data-detail-personal="teachers"]'),
                course: document.querySelector('[data-detail-course="teachers"]'),
                attendance: document.querySelector('[data-detail-attendance="teachers"]'),
                fees: document.querySelector('[data-detail-fees="teachers"]'),
                credentialUsername: document.querySelector('[data-credential-username="teachers"]'),
                credentialPassword: document.querySelector('[data-credential-password="teachers"]'),
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

    const formatCurrency = (value) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(value) || 0);
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
                    <td>
                        <div class="action-group">
                            <button class="action-button primary" data-action="view" data-module="students" data-id="${record.id}">View</button>
                            <button class="action-button" data-action="edit" data-module="students" data-id="${record.id}">Edit</button>
                            <button class="action-button danger" data-action="delete" data-module="students" data-id="${record.id}">Delete</button>
                            <button class="action-button primary" data-action="credentials" data-module="students" data-id="${record.id}">Credentials</button>
                            <button class="action-button" data-action="toggle" data-module="students" data-id="${record.id}">${record.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
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
                    <td>
                        <div class="action-group">
                            <button class="action-button primary" data-action="view" data-module="teachers" data-id="${record.id}">View</button>
                            <button class="action-button" data-action="edit" data-module="teachers" data-id="${record.id}">Edit</button>
                            <button class="action-button danger" data-action="delete" data-module="teachers" data-id="${record.id}">Delete</button>
                            <button class="action-button primary" data-action="credentials" data-module="teachers" data-id="${record.id}">Credentials</button>
                            <button class="action-button" data-action="toggle" data-module="teachers" data-id="${record.id}">${record.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
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
                    <td>
                        <div class="action-group">
                            <button class="action-button primary" data-action="view" data-module="courses" data-id="${record.id}">View</button>
                            <button class="action-button" data-action="edit" data-module="courses" data-id="${record.id}">Edit</button>
                            <button class="action-button danger" data-action="delete" data-module="courses" data-id="${record.id}">Delete</button>
                            <button class="action-button" data-action="toggle" data-module="courses" data-id="${record.id}">${record.is_active ? 'Deactivate' : 'Activate'}</button>
                        </div>
                    </td>
                `;
            }

            tbody.appendChild(row);
        });

        if (!appState.selected[module] && rows.length) {
            appState.selected[module] = rows[0].id;
        }
    };

    const renderDetail = (module) => {
        const record = getSelectedRecord(module);
        const detail = dom.detail[module];

        if (!detail) {
            return;
        }

        if (!record) {
            detail.placeholder.classList.remove('hidden');
            detail.content.classList.add('hidden');
            return;
        }

        detail.placeholder.classList.add('hidden');
        detail.content.classList.remove('hidden');

        if (module === 'students') {
            detail.avatar.textContent = initials(record.full_name);
            detail.title.textContent = record.full_name;
            detail.subtitle.textContent = `${record.username} • ${record.email}`;
            detail.personal.textContent = [
                `Phone: ${record.phone || '-'}`,
                `Address: ${record.address || '-'}`,
                `Gender: ${record.gender || '-'}`,
                `Date of Birth: ${formatDate(record.date_of_birth)}`,
            ].join(' | ');
            detail.course.textContent = [
                `Course: ${record.course_name || '-'}`,
                `Semester: ${record.semester || '-'}`,
                `Course Code: ${record.course_code || '-'}`,
            ].join(' | ');
            detail.attendance.textContent = `Present ${record.attendance?.present || 0} | Absent ${record.attendance?.absent || 0} | Leave ${record.attendance?.leave || 0}`;
            detail.fees.textContent = `Due ${formatCurrency(record.fees?.due || 0)} | Paid ${formatCurrency(record.fees?.paid || 0)} | Status ${record.fees?.status || 'pending'}`;
            detail.credentialUsername.textContent = record.credentials?.username || record.username || '-';
            detail.credentialPassword.textContent = record.credentials?.password && record.credentials.passwordVisible ? record.credentials.password : '********';
            return;
        }

        if (module === 'teachers') {
            detail.avatar.textContent = initials(record.full_name);
            detail.title.textContent = record.full_name;
            detail.subtitle.textContent = `${record.username} • ${record.email}`;
            detail.personal.textContent = `Phone: ${record.phone || '-'}`;
            detail.course.textContent = `Department: ${record.department || '-'} | Qualification: ${record.qualification || '-'}`;
            detail.attendance.textContent = `Joining Date: ${formatDate(record.joining_date)}`;
            detail.fees.textContent = `Assigned Courses: ${record.assigned_courses || 0}`;
            detail.credentialUsername.textContent = record.credentials?.username || record.username || '-';
            detail.credentialPassword.textContent = record.credentials?.password && record.credentials.passwordVisible ? record.credentials.password : '********';
            return;
        }

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
        appState.modal.module = module;
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

        renderForm(module, getRecordForForm(module, recordId));
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
            const value = record ? (record[config.name] ?? '') : (config.defaultValue ?? '');

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
            { name: 'course_id', label: 'Course', type: 'select', options: () => [
                { value: '', label: 'Select Course' },
                ...getCollection('courses').map((course) => ({ value: String(course.id), label: course.course_name })),
            ] },
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

    const saveRecord = () => {
        const module = appState.modal.module;
        const formData = new FormData(dom.modalForm);
        const recordId = appState.modal.recordId;
        const existingRecord = getRecordForForm(module === 'student' ? 'students' : module === 'teacher' ? 'teachers' : 'courses', recordId);
        const collectionName = module === 'student' ? 'students' : module === 'teacher' ? 'teachers' : 'courses';
        const record = existingRecord ? structuredClone(existingRecord) : null;
        const isEdit = appState.modal.mode === 'edit' && record;
        const courseId = formData.get('course_id');
        const courseRecord = getCollection('courses').find((course) => String(course.id) === String(courseId));

        if (collectionName === 'students') {
            const fullName = String(formData.get('full_name') || '').trim();
            const username = String(formData.get('username') || '').trim() || generateUsername(fullName, 'student');
            const password = isEdit && record?.credentials?.password ? record.credentials.password : generatePassword();

            const nextRecord = {
                id: isEdit ? record.id : Date.now(),
                full_name: fullName,
                username,
                email: String(formData.get('email') || '').trim(),
                phone: String(formData.get('phone') || '').trim(),
                address: String(formData.get('address') || '').trim(),
                gender: String(formData.get('gender') || 'other'),
                date_of_birth: String(formData.get('date_of_birth') || ''),
                semester: String(formData.get('semester') || '').trim(),
                profile_photo: String(formData.get('profile_photo') || '').trim(),
                is_active: String(formData.get('is_active') || '1') === '1',
                course_id: courseId ? Number(courseId) : null,
                course_name: courseRecord?.course_name || '',
                course_code: courseRecord?.course_code || '',
                attendance: existingRecord?.attendance || { present: 0, absent: 0, leave: 0 },
                fees: existingRecord?.fees || { due: 0, paid: 0, status: 'pending' },
                credentials: {
                    username,
                    password,
                    passwordVisible: true,
                },
            };

            upsertRecord('students', nextRecord);
        }

        if (collectionName === 'teachers') {
            const fullName = String(formData.get('full_name') || '').trim();
            const username = String(formData.get('username') || '').trim() || generateUsername(fullName, 'teacher');
            const password = isEdit && record?.credentials?.password ? record.credentials.password : generatePassword();

            const nextRecord = {
                id: isEdit ? record.id : Date.now(),
                full_name: fullName,
                username,
                email: String(formData.get('email') || '').trim(),
                phone: String(formData.get('phone') || '').trim(),
                department: String(formData.get('department') || '').trim(),
                qualification: String(formData.get('qualification') || '').trim(),
                joining_date: String(formData.get('joining_date') || ''),
                profile_photo: String(formData.get('profile_photo') || '').trim(),
                is_active: String(formData.get('is_active') || '1') === '1',
                assigned_courses: existingRecord?.assigned_courses || 0,
                credentials: {
                    username,
                    password,
                    passwordVisible: true,
                },
            };

            upsertRecord('teachers', nextRecord);
        }

        if (collectionName === 'courses') {
            const nextRecord = {
                id: isEdit ? record.id : Date.now(),
                course_name: String(formData.get('course_name') || '').trim(),
                course_code: String(formData.get('course_code') || '').trim(),
                duration: String(formData.get('duration') || '').trim(),
                semester_count: Number(formData.get('semester_count') || 0),
                total_fees: Number(formData.get('total_fees') || 0),
                description: String(formData.get('description') || '').trim(),
                is_active: String(formData.get('is_active') || '1') === '1',
                total_students: existingRecord?.total_students || 0,
                total_teachers: existingRecord?.total_teachers || 0,
                assigned_teachers: existingRecord?.assigned_teachers || [],
            };

            upsertRecord('courses', nextRecord);
        }

        closeModal();
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

    const removeRecord = (module, id) => {
        const confirmed = window.confirm(`Delete this ${module.slice(0, -1)}?`);
        if (!confirmed) {
            return;
        }

        state[module] = getCollection(module).filter((record) => Number(record.id) !== Number(id));
        appState.selected[module] = state[module][0]?.id || null;
        renderAll();
    };

    const toggleStatus = (module, id) => {
        state[module] = getCollection(module).map((record) => Number(record.id) === Number(id)
            ? { ...record, is_active: !record.is_active }
            : record);
        renderAll();
    };

    const showRecord = (module, id) => {
        appState.selected[module] = Number(id);
        renderDetail(module);
        setActiveModule(module);
    };

    const openCredentials = (module, id) => {
        const record = getCollection(module).find((entry) => Number(entry.id) === Number(id));
        if (!record) {
            return;
        }

        appState.selected[module] = Number(id);
        if (!record.credentials) {
            record.credentials = { username: record.username || '', password: '', passwordVisible: false };
        }

        renderDetail(module);
    };

    const togglePasswordVisibility = (module) => {
        const record = getSelectedRecord(module);
        if (!record || !record.credentials) {
            return;
        }

        record.credentials.passwordVisible = !record.credentials.passwordVisible;
        renderDetail(module);
    };

    const resetPassword = (module) => {
        const record = getSelectedRecord(module);
        if (!record) {
            return;
        }

        record.credentials = record.credentials || {};
        record.credentials.password = generatePassword();
        record.credentials.passwordVisible = true;
        renderDetail(module);
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
                appState.modal.module = label;
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
            saveRecord();
        });
    };

    const bindTableActions = () => {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) {
                return;
            }

            const action = button.getAttribute('data-action');
            const module = button.getAttribute('data-module');
            const id = button.getAttribute('data-id');

            if (action === 'view') {
                showRecord(module, id);
                return;
            }

            if (action === 'edit') {
                appState.modal.module = module === 'students' ? 'student' : module === 'teachers' ? 'teacher' : 'course';
                appState.modal.mode = 'edit';
                appState.modal.recordId = Number(id);
                openModal(module, 'edit', Number(id));
                return;
            }

            if (action === 'delete') {
                removeRecord(module, id);
                return;
            }

            if (action === 'toggle') {
                toggleStatus(module, id);
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

            const copyButton = target.closest('[data-copy-username], [data-copy-password]');
            if (copyButton) {
                const module = copyButton.getAttribute('data-copy-username') || copyButton.getAttribute('data-copy-password');
                const record = getSelectedRecord(module);
                if (!record) {
                    return;
                }

                const value = copyButton.hasAttribute('data-copy-username') ? (record.credentials?.username || record.username || '') : (record.credentials?.password || '');
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
                resetPassword(resetButton.getAttribute('data-reset-password'));
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
        setActiveModule('students');
        renderAll();
    };

    document.addEventListener('DOMContentLoaded', initialize);
})();