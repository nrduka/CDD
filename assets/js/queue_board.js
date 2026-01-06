(function () {
                const waitingSelect = document.getElementById('waiting-list');
                const activeContainer = document.getElementById('active-list');
                const nowServingBody = document.getElementById('now-serving-body');
                const nowServingPageLabel = document.getElementById('now-serving-page-label');
                const nowServingPrevButton = document.getElementById('now-serving-prev');
                const nowServingNextButton = document.getElementById('now-serving-next');
                const callButton = document.getElementById('call-button');
                const completeButton = document.getElementById('complete-button');
                const returnButton = document.getElementById('return-button');
                const refreshButton = document.getElementById('refresh-button');
                const messageBox = document.getElementById('queue-board-message');
                const registrationLabel = document.getElementById('registration-label');
                const departmentQueuesContainer = document.getElementById('department-queues');
                const departmentQueuesEmpty = document.getElementById('department-queues-empty');
                const finishedBody = document.getElementById('finished-body');
                const finishedEmpty = document.getElementById('finished-empty');
                const tagModalEl = document.getElementById('patient-tag-modal');
                const tagModalTicket = document.getElementById('patient-tag-ticket');
                const tagModalCurrentCode = document.getElementById('patient-tag-current-code');
                const tagModalSearch = document.getElementById('patient-tag-search');
                const tagModalServiceSearch = document.getElementById('patient-tag-service-search');
                const tagModalServiceClear = document.getElementById('patient-tag-service-clear');
                const tagModalServicesSelect = document.getElementById('patient-tag-services');
                const tagModalServicesEmpty = document.getElementById('patient-tag-services-empty');
                const tagModalServiceAdd = document.getElementById('patient-tag-service-add');
                const tagModalServiceRemove = document.getElementById('patient-tag-service-remove');
                const tagModalServicesList = document.getElementById('patient-tag-services-list');
                const tagModalServicesTotal = document.getElementById('patient-tag-services-total');
                const tagModalSubmit = document.getElementById('patient-tag-submit');
                const tagModalSelectedPatient = document.getElementById('patient-tag-selected');
                const tagModalResults = document.getElementById('patient-tag-results');
                const tagModalEmpty = document.getElementById('patient-tag-empty');
                const tagModalLoading = document.getElementById('patient-tag-loading');
                const currentRole = (window.QueueBoardConfig && window.QueueBoardConfig.role) || '';
                const paymentModalEl = document.getElementById('cashier-payment-modal');
                const paymentAlertEl = document.getElementById('cashier-payment-alert');
                const paymentQueueLabelEl = document.getElementById('cashier-payment-queue');
                const paymentPatientEl = document.getElementById('cashier-payment-patient');
                const paymentRetrievalEl = document.getElementById('cashier-payment-retrieval');
                const paymentServicesBody = document.getElementById('cashier-payment-services');
                const paymentTotalEl = document.getElementById('cashier-payment-total');
                const paymentReceiptInput = document.getElementById('cashier-payment-receipt-no');
                const paymentReceivedInput = document.getElementById('cashier-payment-received');
                const paymentSubmitButton = document.getElementById('cashier-payment-submit');
                const chargeSlipModalEl = document.getElementById('charge-slip-modal');
                const chargeSlipAlertEl = document.getElementById('charge-slip-alert');
                const chargeSlipReceiptEl = document.getElementById('charge-slip-receipt');
                const chargeSlipLoadingEl = document.getElementById('charge-slip-loading');
                const chargeSlipPrintButton = document.getElementById('charge-slip-print');
                const chargeSlipCancelButton = document.getElementById('charge-slip-cancel');
                const uploadOpenButton = document.getElementById('patient-upload-open');
                const uploadModalEl = document.getElementById('patient-upload-modal');
                const uploadFileInput = document.getElementById('patient-upload-file');
                const uploadTriggerButton = document.getElementById('patient-upload-trigger');
                const uploadPreviewButton = document.getElementById('patient-upload-preview');
                const uploadImportButton = document.getElementById('patient-upload-import');
                const uploadResetButton = document.getElementById('patient-upload-reset');
                const uploadErrorAlert = document.getElementById('patient-upload-error');
                const uploadWarningAlert = document.getElementById('patient-upload-warning');
                const uploadPreviewWrapper = document.getElementById('patient-upload-preview-wrapper');
                const uploadPreviewBody = document.getElementById('patient-upload-preview-body');
                const uploadPreviewEmpty = document.getElementById('patient-upload-preview-empty');
                const uploadPreviewCount = document.getElementById('patient-upload-preview-count');
                const uploadFilename = document.getElementById('patient-upload-filename');
                const uploadLoading = document.getElementById('patient-upload-loading');

                let refreshTimer = null;
                let selectedActiveIds = [];
                let cashierDepartmentId = null;
                let finishedPatients = [];
                let serviceOptions = [];
                let filteredServiceOptions = [];
                let selectedServiceIds = [];
                let nowServingData = [];
                let nowServingPage = 1;
                const nowServingPageSize = 5;
                let departmentData = [];
                let departmentPages = {};
                const departmentPageSize = 5;
                let tagModalInstance = null;
                let currentTagContext = null;
                let tagSearchTimer = null;
                let selectedPatient = null;
                let paymentModalInstance = null;
                let paymentModalQueueId = null;
                let paymentModalDepartmentQueueId = null;
                let paymentDetails = null;
                let chargeSlipModalInstance = null;
                let chargeSlipData = null;
                let chargeSlipQueueId = null;
                let chargeSlipFallback = null;
                let pendingTagPayload = null;
                let chargeSlipMode = 'final';
                let chargeSlipPrintInProgress = false;
                let restoreTagModalOnClose = false;
                let uploadModalInstance = null;
                let uploadPreviewRows = [];

                function setMessage(text, type = 'info') {
                    if (!messageBox) { return; }
                    if (!text) {
                        messageBox.innerHTML = '';
                        messageBox.removeAttribute('data-message-type');
                        return;
                    }
                    messageBox.innerHTML = '<div class="alert alert-' + type + ' mb-0" role="alert">' + text + '</div>';
                    messageBox.dataset.messageType = type;
                }

                function setUploadAlert(type, text) {
                    if (!uploadErrorAlert || !uploadWarningAlert) { return; }
                    uploadErrorAlert.classList.add('d-none');
                    uploadWarningAlert.classList.add('d-none');
                    if (!text) { return; }
                    if (type === 'error') {
                        uploadErrorAlert.textContent = text;
                        uploadErrorAlert.classList.remove('d-none');
                    } else if (type === 'warning') {
                        uploadWarningAlert.textContent = text;
                        uploadWarningAlert.classList.remove('d-none');
                    }
                }

                function setUploadLoading(isLoading) {
                    if (uploadLoading) {
                        uploadLoading.classList.toggle('d-none', !isLoading);
                    }
                    if (uploadPreviewButton) {
                        uploadPreviewButton.disabled = isLoading;
                    }
                    if (uploadImportButton) {
                        uploadImportButton.disabled = isLoading || !uploadPreviewRows.length;
                    }
                }

                function resetUploadModal(clearFile) {
                    uploadPreviewRows = [];
                    setUploadAlert('', '');
                    if (uploadPreviewWrapper) { uploadPreviewWrapper.classList.add('d-none'); }
                    if (uploadPreviewEmpty) { uploadPreviewEmpty.classList.remove('d-none'); }
                    if (uploadPreviewBody) { uploadPreviewBody.innerHTML = ''; }
                    if (uploadPreviewCount) { uploadPreviewCount.textContent = ''; }
                    if (uploadImportButton) { uploadImportButton.disabled = true; }
                    if (uploadFileInput && clearFile) { uploadFileInput.value = ''; }
                    if (uploadFilename) { uploadFilename.textContent = uploadFileInput && uploadFileInput.files.length ? uploadFileInput.files[0].name : 'No file selected'; }
                }

                function renderUploadPreview(rows) {
                    if (!uploadPreviewBody || !uploadPreviewWrapper || !uploadPreviewEmpty) { return; }
                    uploadPreviewBody.innerHTML = '';
                    if (!rows || !rows.length) {
                        uploadPreviewWrapper.classList.add('d-none');
                        uploadPreviewEmpty.classList.remove('d-none');
                        return;
                    }
                    uploadPreviewWrapper.classList.remove('d-none');
                    uploadPreviewEmpty.classList.add('d-none');

                    rows.forEach(function (row) {
                        const tr = document.createElement('tr');
                        const cells = [
                            row.retrieval_code || '',
                            row.firstname || '',
                            row.lastname || '',
                            row.dob || '',
                            row.sex || '',
                            row.email || '',
                            row.branches || ''
                        ];
                        cells.forEach(function (cell) {
                            const td = document.createElement('td');
                            td.textContent = cell;
                            tr.appendChild(td);
                        });
                        uploadPreviewBody.appendChild(tr);
                    });
                    if (uploadPreviewCount) {
                        uploadPreviewCount.textContent = rows.length + ' row(s) ready to import.';
                    }
                }

                function clearTagModalState(preserveContext) {
                    if (!preserveContext) {
                        currentTagContext = null;
                    }
                    selectedPatient = null;
                    selectedServiceIds = [];
                    filteredServiceOptions = [];
                    if (tagSearchTimer) {
                        clearTimeout(tagSearchTimer);
                        tagSearchTimer = null;
                    }
                    if (tagModalSearch) {
                        tagModalSearch.value = '';
                    }
                    if (tagModalServiceSearch) {
                        tagModalServiceSearch.value = '';
                    }
                    if (tagModalServicesSelect) {
                        tagModalServicesSelect.selectedIndex = -1;
                    }
                    if (tagModalResults) {
                        tagModalResults.innerHTML = '';
                    }
                    if (tagModalEmpty) {
                        tagModalEmpty.textContent = 'Start typing to search for a patient.';
                        tagModalEmpty.classList.remove('d-none');
                    }
                    if (tagModalLoading) {
                        tagModalLoading.classList.add('d-none');
                    }
                    renderServiceOptions(true);
                    renderSelectedServices();
                    updateSelectedPatientLabel();
                    updateTagModalSubmitState();
                }

                function renderServiceOptions(resetSelection) {
                    if (!tagModalServicesSelect) {
                        return;
                    }

                    const previousSelection = resetSelection ? [] : Array.prototype.map.call(tagModalServicesSelect.selectedOptions, function (option) {
                        return option.value;
                    });

                    tagModalServicesSelect.innerHTML = '';

                    const source = Array.isArray(filteredServiceOptions) && filteredServiceOptions.length
                        ? filteredServiceOptions
                        : serviceOptions;

                    if (!Array.isArray(source) || source.length === 0) {
                        tagModalServicesSelect.disabled = true;
                        if (tagModalServicesEmpty) {
                            tagModalServicesEmpty.classList.remove('d-none');
                        }
                        return;
                    }

                    tagModalServicesSelect.disabled = false;
                    if (tagModalServicesEmpty) {
                        tagModalServicesEmpty.classList.add('d-none');
                    }

                    source.forEach(function (item) {
                        if (!item || typeof item.id === 'undefined') {
                            return;
                        }
                        const option = document.createElement('option');
                        option.value = String(item.id);
                        option.textContent = formatServiceLabel(item);
                        if (!resetSelection && previousSelection.indexOf(option.value) !== -1) {
                            option.selected = true;
                        }
                        tagModalServicesSelect.appendChild(option);
                    });
                }

                function renderSelectedServices() {
                    if (!tagModalServicesList) {
                        return;
                    }
                    tagModalServicesList.innerHTML = '';
                    updateSelectedServicesTotal();
                    if (!selectedServiceIds.length) {
                        const empty = document.createElement('div');
                        empty.className = 'text-muted fst-italic small px-2 py-1';
                        empty.textContent = 'No services added.';
                        tagModalServicesList.appendChild(empty);
                        return;
                    }
                    selectedServiceIds.forEach(function (id) {
                        const service = findServiceById(id);
                        const label = service ? formatServiceLabel(service) : ('Service ' + id);
                        const item = document.createElement('label');
                        item.className = 'list-group-item d-flex align-items-center gap-2';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.className = 'form-check-input service-remove-checkbox';
                        checkbox.value = String(id);
                        const text = document.createElement('div');
                        text.textContent = label;
                        item.appendChild(checkbox);
                        item.appendChild(text);
                        tagModalServicesList.appendChild(item);
                    });
                }

                function updateSelectedServicesTotal() {
                    if (!tagModalServicesTotal) { return; }
                    const total = selectedServiceIds.reduce(function (sum, id) {
                        const service = findServiceById(id);
                        const amount = getServiceAmountValue(service);
                        return sum + (Number.isNaN(amount) ? 0 : amount);
                    }, 0);
                    tagModalServicesTotal.textContent = 'Total: ' + formatCurrency(total);
                }

                function getSelectedServiceIds() {
                    return Array.isArray(selectedServiceIds) ? selectedServiceIds.slice() : [];
                }

                function updateSelectedPatientLabel() {
                    if (!tagModalSelectedPatient) { return; }
                    if (selectedPatient && (selectedPatient.full_name || selectedPatient.retrieval_code)) {
                        tagModalSelectedPatient.textContent = selectedPatient.full_name || selectedPatient.retrieval_code;
                    } else {
                        tagModalSelectedPatient.textContent = 'None selected';
                    }
                }

                function updateTagModalSubmitState() {
                    if (!tagModalSubmit) { return; }
                    const hasPatient = !!(selectedPatient && selectedPatient.id);
                    const hasServices = selectedServiceIds.length > 0;
                    tagModalSubmit.disabled = !(hasPatient && hasServices);
                }

                function syncServiceOptions(options, preserveSelection) {
                    serviceOptions = Array.isArray(options) ? options.slice() : [];

                    const currentSearch = (tagModalServiceSearch && tagModalServiceSearch.value)
                        ? tagModalServiceSearch.value.trim()
                        : '';

                    if (preserveSelection && currentSearch) {
                        const searchValue = currentSearch.toLowerCase();
                        filteredServiceOptions = serviceOptions.filter(function (item) {
                            if (!item) { return false; }
                            const haystack = [
                                item.item_code || '',
                                item.item_code_padded || '',
                                item.description || '',
                                item.label || ''
                            ].join(' ').toLowerCase();
                            return haystack.indexOf(searchValue) !== -1;
                        });
                        renderServiceOptions(false);
                    } else {
                        filteredServiceOptions = [];
                        renderServiceOptions(!preserveSelection);
                    }

                    renderSelectedServices();
                    updateTagModalSubmitState();
                }

                function formatDateValue(value) {
                    if (!value) { return ''; }
                    const date = new Date(value);
                    if (!Number.isNaN(date.getTime())) {
                        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                    return value;
                }

                function findServiceById(id) {
                    const lookup = Array.isArray(serviceOptions) ? serviceOptions : [];
                    const match = lookup.find(function (item) { return String(item.id) === String(id); });
                    return match || null;
                }

                function getServiceAmountValue(service) {
                    if (!service) { return 0; }
                    const amountRaw = service.amount;
                    if (amountRaw !== undefined && amountRaw !== null && amountRaw !== '') {
                        const parsedAmount = parseFloat(amountRaw);
                        if (!Number.isNaN(parsedAmount)) {
                            return parsedAmount;
                        }
                    }
                    const labelRaw = service.amount_label;
                    if (labelRaw !== undefined && labelRaw !== null && labelRaw !== '') {
                        const normalized = String(labelRaw).replace(/[^0-9.-]+/g, '');
                        const parsedLabel = parseFloat(normalized);
                        if (!Number.isNaN(parsedLabel)) {
                            return parsedLabel;
                        }
                    }
                    const fullLabel = service.label;
                    if (fullLabel !== undefined && fullLabel !== null && fullLabel !== '') {
                        const match = String(fullLabel).match(/:\s*([0-9][0-9,]*\.?[0-9]*)\s*$/);
                        if (match && match[1]) {
                            const normalized = match[1].replace(/,/g, '');
                            const parsedFromLabel = parseFloat(normalized);
                            if (!Number.isNaN(parsedFromLabel)) {
                                return parsedFromLabel;
                            }
                        }
                    }
                    return 0;
                }

                function formatServiceLabel(service) {
                    if (!service) { return ''; }
                    const description = (service.description || '').toString().trim();
                    const codeValue = service.item_code ? String(service.item_code) : '';
                    const code = codeValue ? codeValue.padStart(5, '0') : '';
                    const amount = service.amount_label ? service.amount_label : '';
                    let label = code || ('Service ' + (service.id || ''));
                    if (description !== '') {
                        label += ' - ' + description;
                    }
                    if (amount) {
                        label += ' : ' + amount;
                    }
                    return label;
                }

                function escapeHtml(value) {
                    if (value === null || value === undefined) {
                        return '';
                    }
                    return String(value).replace(/[&<>"']/g, function (char) {
                        switch (char) {
                            case '&': return '&amp;';
                            case '<': return '&lt;';
                            case '>': return '&gt;';
                            case '"': return '&quot;';
                            case '\'': return '&#039;';
                            default: return char;
                        }
                    });
                }

                function formatCurrency(value) {
                    const amount = typeof value === 'number' ? value : parseFloat(value);
                    if (Number.isNaN(amount)) {
                        return '0.00';
                    }
                    return amount.toFixed(2);
                }

                function formatPatientDisplayName(patient) {
                    if (!patient) { return 'Patient'; }
                    const last = (patient.lastname || patient.last_name || patient.lastName || '').toString().trim();
                    const first = (patient.firstname || patient.first_name || patient.firstName || '').toString().trim();
                    const middle = (patient.middlename || patient.middle_name || patient.middleName || '').toString().trim();
                    if (last || first || middle) {
                        const nameParts = [];
                        if (last) {
                            nameParts.push(last);
                        }
                        if (first) {
                            nameParts.push((last ? ', ' : '') + first);
                        }
                        if (middle) {
                            nameParts.push(middle);
                        }
                        const display = nameParts.join(' ').trim();
                        if (display) {
                            return display;
                        }
                    }
                    if (patient.display_name) {
                        return patient.display_name;
                    }
                    if (patient.full_name) {
                        return patient.full_name;
                    }
                    if (patient.retrieval_code) {
                        return patient.retrieval_code;
                    }
                    return 'Patient';
                }

                function renderTagPatientResults(patients, queryText) {
                    if (!tagModalResults) { return; }
                    tagModalResults.innerHTML = '';
                    const trimmedQuery = (queryText || '').trim();
                    if (!patients || !patients.length) {
                        if (tagModalEmpty) {
                            if (!trimmedQuery) {
                                tagModalEmpty.textContent = 'Start typing to search for a patient.';
                            } else if (trimmedQuery.length < 2) {
                                tagModalEmpty.textContent = 'Type at least 2 characters to search.';
                            } else {
                                tagModalEmpty.textContent = 'No patients found.';
                            }
                            tagModalEmpty.classList.remove('d-none');
                        }
                        return;
                    }
                    if (tagModalEmpty) {
                        tagModalEmpty.classList.add('d-none');
                    }
                    patients.forEach(function (patient) {
                        if (!patient) { return; }
                        const listItem = document.createElement('li');
                        listItem.className = 'list-group-item patient-tag-result';

                        const row = document.createElement('div');
                        row.className = 'patient-tag-result';

                        const details = document.createElement('div');
                        details.className = 'patient-details';

                        const displayName = formatPatientDisplayName(patient);
                        const name = document.createElement('div');
                        name.className = 'fw-semibold';
                        name.textContent = displayName;
                        details.appendChild(name);

                        const metaParts = [];
                        if (patient.retrieval_code) {
                            metaParts.push('Retrieval ' + patient.retrieval_code);
                        }
                        if (patient.dob) {
                            metaParts.push('DOB ' + formatDateValue(patient.dob));
                        }
                        if (patient.sex) {
                            metaParts.push(patient.sex);
                        }
                        if (metaParts.length) {
                            const meta = document.createElement('div');
                            meta.className = 'patient-meta';
                            meta.textContent = metaParts.join(' • ');
                            details.appendChild(meta);
                        }

                        row.appendChild(details);

                        const actionWrapper = document.createElement('div');
                        const selectBtn = document.createElement('button');
                        selectBtn.type = 'button';
                        selectBtn.className = 'btn btn-sm btn-primary patient-tag-select';
                        selectBtn.dataset.patientId = patient.id;
                        selectBtn.dataset.patientName = displayName;
                        selectBtn.dataset.retrievalCode = patient.retrieval_code || '';
                        selectBtn.textContent = 'Select';
                        actionWrapper.appendChild(selectBtn);
                        row.appendChild(actionWrapper);

                        listItem.appendChild(row);
                        tagModalResults.appendChild(listItem);
                    });
                }

                function openTagPatientModal(context) {
                    if (!context || !context.queueId) {
                        setMessage('Unable to tag this queue ticket.', 'warning');
                        return;
                    }
                    const parsedQueueId = parseInt(context.queueId, 10);
                    if (Number.isNaN(parsedQueueId) || parsedQueueId <= 0) {
                        setMessage('Unable to tag this queue ticket.', 'warning');
                        return;
                    }
                    currentTagContext = {
                        queueId: parsedQueueId,
                        callId: context.callId ? parseInt(context.callId, 10) : null,
                        ticket: context.ticket || 'Ticket',
                        retrievalCode: context.retrievalCode || ''
                    };
                    clearTagModalState(true);
                    if (tagModalTicket) {
                        tagModalTicket.textContent = currentTagContext.ticket;
                    }
                    if (tagModalCurrentCode) {
                        if (currentTagContext.retrievalCode) {
                            tagModalCurrentCode.textContent = currentTagContext.retrievalCode;
                            tagModalCurrentCode.classList.remove('text-muted');
                            tagModalCurrentCode.classList.add('text-primary');
                        } else {
                            tagModalCurrentCode.textContent = 'Not tagged';
                            tagModalCurrentCode.classList.remove('text-primary');
                            tagModalCurrentCode.classList.add('text-muted');
                        }
                    }
                    if (tagModalInstance) {
                        tagModalInstance.show();
                    } else if (tagModalEl) {
                        tagModalEl.classList.add('show');
                    }
                    updateTagModalSubmitState();
                    if (tagModalSearch) {
                        tagModalSearch.focus();
                    }
                }

                async function fetchTagPatients(queryText) {
                    if (!tagModalResults) { return; }
                    const trimmed = (queryText || '').trim();
                    if (!trimmed || trimmed.length < 2) {
                        renderTagPatientResults([], trimmed);
                        return;
                    }
                    if (tagModalLoading) {
                        tagModalLoading.classList.remove('d-none');
                    }
                    if (tagModalEmpty) {
                        tagModalEmpty.classList.add('d-none');
                    }
                    tagModalResults.innerHTML = '';
                    try {
                        const params = new URLSearchParams();
                        params.append('action', 'search_patients');
                        params.append('query', trimmed);
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            if (tagModalEmpty) {
                                tagModalEmpty.textContent = data.message || 'Unable to search patients.';
                                tagModalEmpty.classList.remove('d-none');
                            }
                            return;
                        }
                        const patients = Array.isArray(data.patients) ? data.patients : [];
                        renderTagPatientResults(patients, trimmed);
                    } catch (error) {
                        if (tagModalEmpty) {
                            tagModalEmpty.textContent = 'Search failed. Please try again.';
                            tagModalEmpty.classList.remove('d-none');
                        }
                    } finally {
                        if (tagModalLoading) {
                            tagModalLoading.classList.add('d-none');
                        }
                    }
                }

                function buildServiceDetailsFromSelection(serviceIds) {
                    if (!Array.isArray(serviceIds) || !serviceIds.length) {
                        return [];
                    }
                    const details = [];
                    serviceIds.forEach(function (serviceId) {
                        const service = findServiceById(serviceId);
                        if (!service) { return; }
                        const amountValue = getServiceAmountValue(service);
                        details.push({
                            id: serviceId,
                            item_code: service.item_code || '',
                            description: service.description || '',
                            amount: Number.isNaN(amountValue) ? 0 : amountValue,
                            department: service.department_name || service.department || '',
                            department_code: service.department_code || ''
                        });
                    });
                    return details;
                }

                function collectTagSubmissionPayload() {
                    if (!currentTagContext || !currentTagContext.queueId) {
                        setMessage('No queue ticket selected for tagging.', 'warning');
                        return null;
                    }
                    if (!selectedPatient || !selectedPatient.id) {
                        setMessage('Select a patient first.', 'warning');
                        return null;
                    }
                    if (!serviceOptions.length) {
                        setMessage('No services are available to assign. Please configure services first.', 'warning');
                        return null;
                    }
                    const selectedServices = getSelectedServiceIds();
                    if (!selectedServices.length) {
                        setMessage('Select at least one service for this patient.', 'warning');
                        return null;
                    }
                    const serviceDetails = buildServiceDetailsFromSelection(selectedServices);
                    if (!serviceDetails.length) {
                        setMessage('Selected services could not be resolved.', 'warning');
                        return null;
                    }
                    return {
                        queueId: currentTagContext.queueId,
                        callId: currentTagContext.callId,
                        ticketLabel: currentTagContext.ticket || 'Ticket',
                        patientId: selectedPatient.id,
                        patientName: selectedPatient.full_name || selectedPatient.retrieval_code || 'Patient',
                        retrievalCode: selectedPatient.retrieval_code || '',
                        serviceIds: selectedServices.slice(),
                        serviceDetails: serviceDetails
                    };
                }

                function buildChargeSlipDetailsFromPayload(payload) {
                    if (!payload) { return null; }
                    const services = payload.serviceDetails.map(function (detail) {
                        return {
                            item_code: detail.item_code,
                            description: detail.description,
                            department: detail.department,
                            department_code: detail.department_code,
                            amount: detail.amount
                        };
                    });
                    const total = services.reduce(function (sum, detail) {
                        const amount = typeof detail.amount === 'number' ? detail.amount : parseFloat(detail.amount || '0');
                        return sum + (Number.isNaN(amount) ? 0 : amount);
                    }, 0);
                    return {
                        queue_id: payload.queueId,
                        queue_label: payload.ticketLabel,
                        ticket_number: payload.ticketLabel,
                        retrieval_code: payload.retrievalCode,
                        patient_name: payload.patientName,
                        services: services,
                        total_amount: total
                    };
                }

                function beginChargeSlipPreview() {
                    const payload = collectTagSubmissionPayload();
                    if (!payload) {
                        return;
                    }
                    const confirmed = window.confirm('Please confirm the patient details and charges before printing the slip.');
                    if (!confirmed) {
                        return;
                    }
                    const stagedDetails = buildChargeSlipDetailsFromPayload(payload);
                    const fallbackSlip = {
                        patientName: payload.patientName,
                        queueLabel: payload.ticketLabel,
                        retrievalCode: payload.retrievalCode
                    };
                    if (tagModalInstance) {
                        tagModalInstance.hide();
                    } else if (tagModalEl) {
                        tagModalEl.classList.remove('show');
                    }
                    openChargeSlipModal({
                        queueId: payload.queueId,
                        fallbackData: fallbackSlip,
                        stagedDetails: stagedDetails,
                        pendingPayload: payload,
                        restoreTag: true
                    });
                }

                async function submitTagPayload(payload) {
                    if (!payload) {
                        return { success: false, message: 'Missing tag details.' };
                    }
                    const params = new URLSearchParams();
                    params.append('action', 'tag_patient');
                    params.append('queue_id', payload.queueId);
                    params.append('patient_id', payload.patientId);
                    if (payload.callId) {
                        params.append('call_id', payload.callId);
                    }
                    payload.serviceIds.forEach(function (serviceId) {
                        params.append('services[]', serviceId);
                    });
                    try {
                        if (tagModalLoading) {
                            tagModalLoading.classList.remove('d-none');
                        }
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            return { success: false, message: data.message || 'Unable to tag patient.' };
                        }
                        setMessage(data.message || 'Patient tagged successfully.', 'success');
                        clearTagModalState(false);
                        await fetchQueues(false);
                        return { success: true, data: data };
                    } catch (error) {
                        return { success: false, message: 'Unable to tag patient. Please try again.' };
                    } finally {
                        if (tagModalLoading) {
                            tagModalLoading.classList.add('d-none');
                        }
                    }
                }

                if (tagModalEl && typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                    tagModalInstance = new bootstrap.Modal(tagModalEl);
                    tagModalEl.addEventListener('hidden.bs.modal', function () {
                        clearTagModalState(false);
                    });
                    tagModalEl.addEventListener('shown.bs.modal', function () {
                        if (tagModalSearch) {
                            tagModalSearch.focus();
                        }
                    });
                }

                if (tagModalSearch) {
                    tagModalSearch.addEventListener('input', function (event) {
                        const query = event.target.value || '';
                        if (tagSearchTimer) {
                            clearTimeout(tagSearchTimer);
                        }
                        tagSearchTimer = setTimeout(function () {
                            fetchTagPatients(query);
                        }, 250);
                    });
                }

                if (tagModalResults) {
                    tagModalResults.addEventListener('click', function (event) {
                        const selectBtn = event.target.closest('.patient-tag-select');
                        if (!selectBtn) { return; }
                        event.preventDefault();
                        const patientId = parseInt(selectBtn.dataset.patientId || '0', 10);
                        if (!patientId) { return; }
                        const patientName = selectBtn.dataset.patientName || '';
                        const retrievalCode = selectBtn.dataset.retrievalCode || '';
                        selectedPatient = {
                            id: patientId,
                            full_name: patientName,
                            retrieval_code: retrievalCode
                        };
                        updateSelectedPatientLabel();
                        updateTagModalSubmitState();
                    });
                }

                if (tagModalServiceSearch) {
                    tagModalServiceSearch.addEventListener('input', function (event) {
                        const raw = event.target.value || '';
                        const searchValue = raw.toUpperCase();
                        event.target.value = searchValue;
                        if (!searchValue) {
                            filteredServiceOptions = [];
                            renderServiceOptions(true);
                            return;
                        }
                        filteredServiceOptions = serviceOptions.filter(function (item) {
                            if (!item) { return false; }
                            const haystack = [
                                item.description || '',
                                item.item_code || '',
                                item.label || ''
                            ].join(' ').toUpperCase();
                            return haystack.indexOf(searchValue) !== -1;
                        });
                        renderServiceOptions(true);
                    });
                }

                if (tagModalServiceClear) {
                    tagModalServiceClear.addEventListener('click', function () {
                        if (tagModalServiceSearch) {
                            tagModalServiceSearch.value = '';
                        }
                        filteredServiceOptions = [];
                        renderServiceOptions(true);
                    });
                }

                if (tagModalServiceAdd) {
                    tagModalServiceAdd.addEventListener('click', function () {
                        if (!tagModalServicesSelect || !tagModalServicesSelect.value) {
                            setMessage('Select a service to add.', 'warning');
                            return;
                        }
                        const serviceId = tagModalServicesSelect.value;
                        if (selectedServiceIds.indexOf(serviceId) !== -1) {
                            setMessage('Service already added.', 'info');
                            return;
                        }
                        selectedServiceIds.push(serviceId);
                        renderSelectedServices();
                        updateSelectedServicesTotal();
                        updateTagModalSubmitState();
                    });
                }

                if (tagModalServiceRemove) {
                    tagModalServiceRemove.addEventListener('click', function () {
                        if (!tagModalServicesList) { return; }
                        const checkboxes = tagModalServicesList.querySelectorAll('.service-remove-checkbox:checked');
                        if (!checkboxes.length) {
                            setMessage('Select at least one service to remove.', 'warning');
                            return;
                        }
                        const removeIds = Array.prototype.map.call(checkboxes, function (cb) { return cb.value; });
                        selectedServiceIds = selectedServiceIds.filter(function (id) {
                            return removeIds.indexOf(String(id)) === -1;
                        });
                        renderSelectedServices();
                        updateSelectedServicesTotal();
                        updateTagModalSubmitState();
                    });
                }

                if (tagModalSubmit) {
                    tagModalSubmit.addEventListener('click', function () {
                        beginChargeSlipPreview();
                    });
                }

                function populateSelect(selectEl, items, valueKey, labelKey) {
                    if (!selectEl) { return; }
                    selectEl.innerHTML = '';
                    items.forEach(function (item) {
                        const option = document.createElement('option');
                        option.value = item[valueKey];
                        option.textContent = item[labelKey];
                        if (item.queue_id) {
                            option.dataset.queueId = item.queue_id;
                        }
                        if (item.called_at) {
                            option.dataset.calledAt = item.called_at;
                        }
                        if (item.retrieval_code) {
                            option.dataset.retrievalCode = item.retrieval_code;
                        }
                        selectEl.appendChild(option);
                    });
                }

                // Registration routed list removed.

                function renderActiveList(items) {
                    if (!activeContainer) { return; }
                    const previousSelection = new Set(selectedActiveIds);
                    selectedActiveIds = [];
                    activeContainer.innerHTML = '';

                    if (!items.length) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'text-muted small text-center py-4';
                        emptyState.textContent = 'No active calls.';
                        activeContainer.appendChild(emptyState);
                        return;
                    }

                    items.forEach(function (item) {
                        const callId = String(item.call_id);
                        const isSelected = previousSelection.has(callId);
                        if (isSelected) {
                            selectedActiveIds.push(callId);
                        }

                        const wrapper = document.createElement('div');
                        wrapper.className = 'queue-active-item' + (isSelected ? ' active' : '');
                        wrapper.dataset.callId = callId;
                        if (item.queue_id) {
                            wrapper.dataset.queueId = String(item.queue_id);
                        }

                        const details = document.createElement('div');
                        details.className = 'queue-details';

                        const primaryLabel = item.ticket_number || item.reference || item.label || 'Ticket';
                        const label = document.createElement('div');
                        label.className = 'queue-label';
                        label.textContent = primaryLabel;
                        details.appendChild(label);

                        const infoParts = [];
                        if (item.registration_number) {
                            infoParts.push('Registration ' + item.registration_number);
                        }
                        if (item.category_label) {
                            infoParts.push(item.category_label);
                        }
                        if (item.unique_code) {
                            infoParts.push(item.unique_code);
                        } else if (item.reference && item.reference !== primaryLabel) {
                            infoParts.push(item.reference);
                        }
                        if (infoParts.length) {
                            const infoMeta = document.createElement('div');
                            infoMeta.className = 'queue-meta';
                            infoMeta.textContent = infoParts.join(' - ');
                            details.appendChild(infoMeta);
                        }

                        if (item.called_at) {
                            const meta = document.createElement('div');
                            meta.className = 'queue-meta';
                            meta.textContent = 'Last called: ' + formatTimestampLabel(item.called_at);
                            details.appendChild(meta);
                        }

                        const patientMeta = document.createElement('div');
                        patientMeta.className = 'queue-meta patient-tag-meta' + (item.retrieval_code ? '' : ' text-muted fst-italic');
                        patientMeta.textContent = item.retrieval_code ? ('Patient tag: ' + item.retrieval_code) : 'No patient tag assigned';
                        details.appendChild(patientMeta);

                        const actions = document.createElement('div');
                        actions.className = 'queue-item-actions';

                        const tagButton = document.createElement('button');
                        tagButton.type = 'button';
                        tagButton.className = 'queue-tag-btn';
                        tagButton.dataset.callId = callId;
                        tagButton.dataset.ticket = primaryLabel;
                        if (item.queue_id) {
                            tagButton.dataset.queueId = String(item.queue_id);
                        }
                        if (item.retrieval_code) {
                            tagButton.dataset.currentCode = item.retrieval_code;
                        }
                        tagButton.title = item.retrieval_code ? 'Update patient tag' : 'Tag patient record';
                        tagButton.innerHTML = '<i class="ri-user-search-line"></i>';
                        tagButton.disabled = !item.queue_id;

                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'announce-btn';
                        button.dataset.callId = callId;
                        button.innerHTML = '<i class="ri-phone-fill"></i>';

                        actions.appendChild(tagButton);
                        actions.appendChild(button);

                        wrapper.appendChild(details);
                        wrapper.appendChild(actions);
                        activeContainer.appendChild(wrapper);
                    });
                }

                function renderRoutedList(items) {
                    nowServingData = Array.isArray(items) ? items.slice() : [];
                    nowServingPage = 1;
                    renderNowServingPage();
                }

                function renderNowServingPage() {
                    if (!nowServingBody) { return; }
                    const totalItems = nowServingData.length;
                    const totalPages = Math.max(1, Math.ceil(totalItems / nowServingPageSize));
                    if (nowServingPage > totalPages) {
                        nowServingPage = totalPages;
                    }
                    if (nowServingPage < 1) {
                        nowServingPage = 1;
                    }

                    if (nowServingPageLabel) {
                        nowServingPageLabel.textContent = 'Page ' + nowServingPage + ' of ' + totalPages;
                    }
                    if (nowServingPrevButton) {
                        nowServingPrevButton.disabled = nowServingPage <= 1 || totalItems === 0;
                    }
                    if (nowServingNextButton) {
                        nowServingNextButton.disabled = nowServingPage >= totalPages || totalItems === 0;
                    }

                    nowServingBody.innerHTML = '';
                    if (!totalItems) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'text-center text-muted py-4';
                        emptyState.textContent = 'No customers currently routed.';
                        nowServingBody.appendChild(emptyState);
                        return;
                    }

                    const startIndex = (nowServingPage - 1) * nowServingPageSize;
                    const pageItems = nowServingData.slice(startIndex, startIndex + nowServingPageSize);

                    pageItems.forEach(function (item) {
                        const routes = Array.isArray(item.routes) ? item.routes : [];
                        const destinationNames = routes.map(function (route) {
                            if (!route) { return ''; }
                            return route.department_name || 'Department';
                        }).filter(Boolean);
                        const primaryRoute = routes.length ? routes[0] : null;
                        const statusClass = mapRouteStatusClass(primaryRoute && primaryRoute.status);
                        const statusLabel = formatRouteStatus(primaryRoute && primaryRoute.status);
                        const statusTime = primaryRoute
                            ? (primaryRoute.status === 'CALLED'
                                ? formatTimestampLabel(primaryRoute.called_at)
                                : formatTimestampLabel(primaryRoute.routed_at))
                            : '';

                        const row = document.createElement('div');
                        row.className = 'now-serving-row';

                        const ticketCell = document.createElement('div');
                        ticketCell.className = 'now-serving-cell';
                        const ticketLabel = document.createElement('div');
                        ticketLabel.className = 'now-serving-ticket';
                        const ticketText = item.ticket_number || item.reference || item.label || 'Ticket';
                        ticketLabel.textContent = ticketText;
                        ticketCell.appendChild(ticketLabel);
                        if (item.called_at) {
                            const ticketMeta = document.createElement('span');
                            ticketMeta.className = 'now-serving-meta';
                            ticketMeta.textContent = 'Last called: ' + formatTimestampLabel(item.called_at);
                            ticketCell.appendChild(ticketMeta);
                        }
                        row.appendChild(ticketCell);

                        const destinationCell = document.createElement('div');
                        destinationCell.className = 'now-serving-cell';
                        destinationCell.innerHTML = '<div class="now-serving-destination">' + (destinationNames.length
                            ? destinationNames.join(', ')
                            : 'Awaiting routing') + '</div>';
                        row.appendChild(destinationCell);

                        const statusCell = document.createElement('div');
                        const statusBadge = document.createElement('span');
                        statusBadge.className = 'now-serving-status-badge' + (statusClass ? ' ' + statusClass : '');
                        statusBadge.textContent = statusLabel || 'Pending';
                        statusCell.appendChild(statusBadge);
                        if (statusTime) {
                            const statusMeta = document.createElement('span');
                            statusMeta.className = 'now-serving-meta';
                            statusMeta.textContent = statusTime;
                            statusCell.appendChild(statusMeta);
                        }
                        row.appendChild(statusCell);

                        const actionCell = document.createElement('div');
                        actionCell.className = 'now-serving-actions';
                        const callBtn = document.createElement('button');
                        callBtn.type = 'button';
                        callBtn.className = 'btn btn-outline-primary btn-sm queue-routed-call';
                        callBtn.dataset.callId = String(item.call_id || '');
                        callBtn.innerHTML = '<i class="ri-phone-line me-1"></i>Call Back';
                        actionCell.appendChild(callBtn);
                        row.appendChild(actionCell);

                        nowServingBody.appendChild(row);
                    });
                }

                function formatTimestampLabel(timestamp) {
                    if (!timestamp) {
                        return '';
                    }
                    const sanitized = String(timestamp).replace(' ', 'T');
                    const parsed = new Date(sanitized);
                    if (Number.isNaN(parsed.getTime())) {
                        return timestamp;
                    }
                    return parsed.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }

                function mapRouteStatusClass(status) {
                    const normalized = String(status || '').toUpperCase();
                    if (normalized === 'WAITING') {
                        return 'waiting';
                    }
                    if (normalized === 'CALLED') {
                        return 'called';
                    }
                    return '';
                }

                function formatRouteStatus(status) {
                    const normalized = String(status || '').toUpperCase();
                    if (normalized === 'WAITING') {
                        return 'Waiting';
                    }
                    if (normalized === 'CALLED') {
                        return 'Being served';
                    }
                    return normalized || 'Unknown';
                }

                const ICON_PATHS = {
                    call: [
                        'M22 16.92v3a2 2 0 0 1-2.18 2 19.86 19.86 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.86 19.86 0 0 1-3.07-8.63A2 2 0 0 1 4.23 2h3a2 2 0 0 1 2 1.72 12.44 12.44 0 0 0 .67 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.44 12.44 0 0 0 2.81.67A2 2 0 0 1 22 16.92z'
                    ],
                    check: ['M5 13l4 4L19 7'],
                    flag: ['M5 3h11l1 2h4v11h-4l-1-2H7v7H5z']
                };

                function createIconSvg(iconName) {
                    const xmlns = 'http://www.w3.org/2000/svg';
                    const svg = document.createElementNS(xmlns, 'svg');
                    svg.setAttribute('viewBox', '0 0 24 24');
                    svg.setAttribute('aria-hidden', 'true');
                    svg.setAttribute('focusable', 'false');
                    svg.classList.add('department-ticket-icon');

                    const paths = ICON_PATHS[iconName] || [];
                    paths.forEach(function (d) {
                        const path = document.createElementNS(xmlns, 'path');
                        path.setAttribute('d', d);
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke', 'currentColor');
                        path.setAttribute('stroke-width', '2');
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('stroke-linejoin', 'round');
                        svg.appendChild(path);
                    });

                    return svg;
                }

                function buildDepartmentTicketItem(deptId, ticket) {
                    const item = document.createElement('div');
                    item.className = 'department-ticket-item';
                    item.dataset.departmentId = String(deptId);
                    item.dataset.recordId = String(ticket.record_id || '');
                    item.dataset.status = ticket.status || '';

                    const isActive = String(ticket.status || '').toUpperCase() === 'CALLED';
                    const paymentStatus = typeof ticket.payment_status !== 'undefined' ? parseInt(ticket.payment_status, 10) : null;
                    const isPaid = paymentStatus === 1;
                    const isUnpaid = paymentStatus === 0;
                    const isCashier = cashierDepartmentId !== null && String(cashierDepartmentId) === String(deptId);
                    if (isActive) {
                        item.classList.add('is-active');
                    }
                    if (isPaid) {
                        item.classList.add('paid');
                    } else if (isUnpaid) {
                        item.classList.add('unpaid');
                    }

                    const info = document.createElement('div');
                    info.className = 'department-ticket-info';

                    const topRow = document.createElement('div');
                    topRow.className = 'department-ticket-top';

                    const label = document.createElement('span');
                    label.className = 'department-ticket-label';
                    label.textContent = ticket.ticket_number || ticket.queue_label || 'Ticket';
                    topRow.appendChild(label);

                    const status = document.createElement('span');
                    status.className = 'badge rounded-pill department-ticket-status ' + (isActive ? 'bg-primary' : 'bg-secondary');
                    status.textContent = isActive ? 'Calling' : 'Waiting';
                    topRow.appendChild(status);

                    info.appendChild(topRow);

                    const metaParts = [];
                    if (ticket.category_label) {
                        metaParts.push(ticket.category_label);
                    }
                    if (ticket.unique_code) {
                        metaParts.push(ticket.unique_code);
                    }
                    if (isActive) {
                        if (ticket.called_at) {
                            metaParts.push('Called ' + formatTimestampLabel(ticket.called_at));
                        }
                        if (ticket.registration_number) {
                            metaParts.push('Registration ' + ticket.registration_number);
                        }
                    } else if (ticket.routed_at) {
                        metaParts.push('Waiting since ' + formatTimestampLabel(ticket.routed_at));
                    }
                    if (isUnpaid) {
                        metaParts.push('Payment: Unpaid');
                    } else if (isPaid) {
                        metaParts.push('Payment: Paid');
                    }

                    if (metaParts.length) {
                        const meta = document.createElement('span');
                        meta.className = 'department-ticket-meta';
                        meta.textContent = metaParts.join(' - ');
                        info.appendChild(meta);
                    }

                    item.appendChild(info);

                    const actions = document.createElement('div');
                    actions.className = 'department-ticket-actions';

                    const callButton = document.createElement('button');
                    callButton.type = 'button';
                    callButton.className = 'department-ticket-action action-call';
                    callButton.dataset.departmentId = String(deptId);
                    callButton.dataset.recordId = String(ticket.record_id || '');
                    callButton.title = 'Call / Announce ticket';
                    if (isUnpaid && !isCashier) {
                        callButton.disabled = true;
                        callButton.title = 'Awaiting payment. Please direct to cashier.';
                    }
                    callButton.appendChild(createIconSvg('call'));
                    actions.appendChild(callButton);

                    if (!isCashier) {
                        const flagButton = document.createElement('button');
                        flagButton.type = 'button';
                        flagButton.className = 'department-ticket-action action-flag';
                        flagButton.dataset.departmentId = String(deptId);
                        flagButton.dataset.recordId = String(ticket.record_id || '');
                        flagButton.title = 'Finished / done (no further routing)';
                        if (!isActive) {
                            flagButton.disabled = true;
                        }
                        flagButton.appendChild(createIconSvg('flag'));
                        actions.appendChild(flagButton);
                    }

                    if (isCashier && isUnpaid) {
                        const payButton = document.createElement('button');
                        payButton.type = 'button';
                        payButton.className = 'department-ticket-action action-pay';
                        payButton.dataset.departmentId = String(deptId);
                        payButton.dataset.recordId = String(ticket.record_id || '');
                        payButton.dataset.queueId = String(ticket.queue_id || '');
                        payButton.textContent = 'Pay';
                        payButton.title = 'Record payment';
                        actions.appendChild(payButton);
                    }

                    item.appendChild(actions);
                    return item;
                }

                function renderDepartmentQueues(departments) {
                    if (!departmentQueuesContainer) { return; }
                    departmentData = Array.isArray(departments) ? departments : [];
                    departmentQueuesContainer.innerHTML = '';

                    if (!departmentData.length) {
                        departmentPages = {};
                        if (departmentQueuesEmpty) {
                            departmentQueuesEmpty.classList.remove('d-none');
                        }
                        return;
                    }

                    if (departmentQueuesEmpty) {
                        departmentQueuesEmpty.classList.add('d-none');
                    }

                    departmentData.forEach(function (dept) {
                        if (!dept || typeof dept.id === 'undefined') {
                            return;
                        }
                        const deptId = String(dept.id);

                        const col = document.createElement('div');
                        col.className = 'col-xl-4 col-lg-6';

                        const card = document.createElement('div');
                        card.className = 'department-card';
                        card.dataset.departmentId = deptId;

                        const header = document.createElement('div');
                        header.className = 'd-flex justify-content-between align-items-start gap-2';

                        const title = document.createElement('h6');
                        title.textContent = dept.name || 'Department';
                        header.appendChild(title);

                        const waitBadge = document.createElement('span');
                        waitBadge.className = 'badge bg-secondary text-light';
                        const waitingCount = typeof dept.waiting_count === 'number'
                            ? dept.waiting_count
                            : ((dept.waiting && dept.waiting.length) || 0);
                        waitBadge.textContent = waitingCount + ' waiting';
                        header.appendChild(waitBadge);

                        card.appendChild(header);

                        const activeLabel = document.createElement('div');
                        activeLabel.className = 'department-active-label';
                        const currentEntry = (dept.current) || ((dept.active && dept.active[0]) || null);
                        activeLabel.textContent = currentEntry
                            ? ('Currently calling: ' + (currentEntry.ticket_number || currentEntry.queue_label))
                            : 'Currently calling: none';
                        card.appendChild(activeLabel);

                        const list = document.createElement('div');
                        list.className = 'department-ticket-list';
                        const tickets = Array.isArray(dept.tickets) && dept.tickets.length
                            ? dept.tickets
                            : ((dept.active || []).concat(dept.waiting || []));
                        const totalPages = Math.max(1, Math.ceil(tickets.length / departmentPageSize));
                        const currentPage = Math.min(totalPages, Math.max(1, departmentPages[deptId] || 1));
                        departmentPages[deptId] = currentPage;
                        const startIndex = (currentPage - 1) * departmentPageSize;
                        const pageTickets = tickets.slice(startIndex, startIndex + departmentPageSize);

                        if (pageTickets.length) {
                            pageTickets.forEach(function (ticket) {
                                if (!ticket || typeof ticket.record_id === 'undefined') {
                                    return;
                                }
                                list.appendChild(buildDepartmentTicketItem(deptId, ticket));
                            });
                        } else {
                            const empty = document.createElement('div');
                            empty.className = 'department-ticket-empty';
                            empty.textContent = 'No customers waiting.';
                            list.appendChild(empty);
                        }
                        card.appendChild(list);

                        if (totalPages > 1) {
                            const pagination = document.createElement('div');
                            pagination.className = 'dept-pagination';

                            const prevBtn = document.createElement('button');
                            prevBtn.type = 'button';
                            prevBtn.className = 'btn btn-outline-secondary btn-sm dept-page-prev';
                            prevBtn.textContent = 'Previous';
                            prevBtn.dataset.departmentId = deptId;
                            prevBtn.disabled = currentPage <= 1;
                            pagination.appendChild(prevBtn);

                            const label = document.createElement('span');
                            label.className = 'text-muted small';
                            label.textContent = 'Page ' + currentPage + ' of ' + totalPages;
                            pagination.appendChild(label);

                            const nextBtn = document.createElement('button');
                            nextBtn.type = 'button';
                            nextBtn.className = 'btn btn-outline-secondary btn-sm dept-page-next';
                            nextBtn.textContent = 'Next';
                            nextBtn.dataset.departmentId = deptId;
                            nextBtn.disabled = currentPage >= totalPages;
                            pagination.appendChild(nextBtn);

                            card.appendChild(pagination);
                        }
                        col.appendChild(card);
                        departmentQueuesContainer.appendChild(col);
                    });
                }

                function renderFinishedPatients(list) {
                    if (!finishedBody || !finishedEmpty) { return; }
                    finishedBody.innerHTML = '';
                    if (!Array.isArray(list) || !list.length) {
                        finishedBody.classList.add('d-none');
                        finishedEmpty.classList.remove('d-none');
                        return;
                    }
                    finishedBody.classList.remove('d-none');
                    finishedEmpty.classList.add('d-none');

                    list.forEach(function (item) {
                        if (!item) { return; }
                        const row = document.createElement('div');
                        row.className = 'now-serving-row';

                        const ticket = document.createElement('div');
                        ticket.className = 'now-serving-ticket';
                        ticket.textContent = item.ticket_number || item.reference || 'Ticket';
                        row.appendChild(ticket);

                        const retrieval = document.createElement('div');
                        retrieval.className = 'now-serving-destination';
                        retrieval.textContent = item.retrieval_code || '—';
                        row.appendChild(retrieval);

                        const completed = document.createElement('div');
                        completed.className = 'now-serving-destination';
                        completed.textContent = item.completed_at ? formatTimestampLabel(item.completed_at) : '';
                        row.appendChild(completed);

                        const actions = document.createElement('div');
                        actions.className = 'now-serving-actions';
                        row.appendChild(actions);

                        finishedBody.appendChild(row);
                    });
                }

                function resetPaymentModal(clearAmount) {
                    paymentDetails = null;
                    paymentModalQueueId = null;
                    paymentModalDepartmentQueueId = null;
                    if (paymentAlertEl) {
                        paymentAlertEl.classList.add('d-none');
                        paymentAlertEl.textContent = '';
                    }
                    if (paymentQueueLabelEl) { paymentQueueLabelEl.textContent = ''; }
                    if (paymentPatientEl) { paymentPatientEl.textContent = ''; }
                    if (paymentRetrievalEl) { paymentRetrievalEl.textContent = ''; }
                    if (paymentServicesBody) {
                        paymentServicesBody.innerHTML = '<tr><td colspan="2" class="text-muted text-center">No services found.</td></tr>';
                    }
                    if (paymentTotalEl) { paymentTotalEl.textContent = '0.00'; }
                    if (paymentReceiptInput) {
                        paymentReceiptInput.value = '';
                    }
                    if (paymentReceivedInput && clearAmount) {
                        paymentReceivedInput.value = '';
                    }
                    if (paymentSubmitButton) {
                        paymentSubmitButton.disabled = false;
                    }
                }

                function populatePaymentDetails(details) {
                    if (!details) { return; }
                    const services = Array.isArray(details.services) ? details.services : [];
                    if (paymentQueueLabelEl) {
                        paymentQueueLabelEl.textContent = details.queue_label || '';
                    }
                    if (paymentPatientEl) {
                        paymentPatientEl.textContent = details.patient_name || 'Unknown';
                    }
                    if (paymentRetrievalEl) {
                        paymentRetrievalEl.textContent = details.retrieval_code || '';
                    }
                    if (paymentReceiptInput && details.receipt_no) {
                        paymentReceiptInput.value = details.receipt_no;
                    }
                    if (paymentServicesBody) {
                        if (!services.length) {
                            paymentServicesBody.innerHTML = '<tr><td colspan="2" class="text-muted text-center">No services found.</td></tr>';
                        } else {
                            paymentServicesBody.innerHTML = '';
                            services.forEach(function (service) {
                                const row = document.createElement('tr');
                                const desc = document.createElement('td');
                                const amount = document.createElement('td');
                                desc.textContent = service.description || service.item_code || 'Service';
                                amount.className = 'text-end';
                                amount.textContent = typeof service.amount === 'number' ? service.amount.toFixed(2) : '0.00';
                                row.appendChild(desc);
                                row.appendChild(amount);
                                paymentServicesBody.appendChild(row);
                            });
                        }
                    }
                    if (paymentTotalEl) {
                        const total = typeof details.total_amount === 'number' ? details.total_amount : 0;
                        paymentTotalEl.textContent = total.toFixed(2);
                        const paidValue = typeof details.total_amount_paid === 'number' ? details.total_amount_paid : null;
                        const suggestedAmount = paidValue !== null ? paidValue : total;
                        if (paymentReceivedInput && paymentReceivedInput.value === '') {
                            paymentReceivedInput.value = suggestedAmount.toFixed(2);
                        }
                    }
                }

                function resetChargeSlipModal() {
                    chargeSlipData = null;
                    chargeSlipQueueId = null;
                    chargeSlipFallback = null;
                    pendingTagPayload = null;
                    chargeSlipMode = 'final';
                    chargeSlipPrintInProgress = false;
                    restoreTagModalOnClose = false;
                    if (chargeSlipAlertEl) {
                        chargeSlipAlertEl.className = 'alert alert-danger d-none';
                        chargeSlipAlertEl.textContent = '';
                    }
                    if (chargeSlipLoadingEl) {
                        chargeSlipLoadingEl.classList.add('d-none');
                    }
                    if (chargeSlipReceiptEl) {
                        chargeSlipReceiptEl.innerHTML = '<div class="text-muted text-center small">No details loaded.</div>';
                    }
                }

                function showChargeSlipInfo(message) {
                    if (!chargeSlipAlertEl) { return; }
                    chargeSlipAlertEl.classList.remove('alert-danger');
                    chargeSlipAlertEl.classList.add('alert-info');
                    chargeSlipAlertEl.textContent = message;
                    chargeSlipAlertEl.classList.remove('d-none');
                }

                function showChargeSlipError(message) {
                    if (!chargeSlipAlertEl) { return; }
                    chargeSlipAlertEl.classList.remove('alert-info');
                    chargeSlipAlertEl.classList.add('alert-danger');
                    chargeSlipAlertEl.textContent = message || 'Unable to prepare the charge slip.';
                    chargeSlipAlertEl.classList.remove('d-none');
                }

                function getChargeSlipDepartmentLabel(service) {
                    if (!service) { return 'N/A'; }
                    const deptName = (service.department || '').toString().trim();
                    const deptCode = (service.department_code || '').toString().trim();
                    if (deptName && deptCode) {
                        return deptCode + ' - ' + deptName;
                    }
                    return deptName || deptCode || 'N/A';
                }

                function renderChargeSlip(details, fallback) {
                    if (!chargeSlipReceiptEl) { return; }
                    const fallbackData = fallback || chargeSlipFallback || {};
                    const patientName = (details && details.patient_name) || fallbackData.patientName || 'Patient';
                    const ticketNumber = (details && (details.ticket_number || details.queue_label)) || fallbackData.queueLabel || '';
                    const retrievalCode = (details && details.retrieval_code) || fallbackData.retrievalCode || '';
                    const services = (details && Array.isArray(details.services)) ? details.services : [];
                    const total = details && typeof details.total_amount === 'number'
                        ? details.total_amount
                        : services.reduce(function (sum, svc) {
                            const amt = typeof svc.amount === 'number' ? svc.amount : parseFloat(svc.amount || '0');
                            return sum + (Number.isNaN(amt) ? 0 : amt);
                        }, 0);
                    const rows = services.length
                        ? services.map(function (service) {
                            const desc = service.description || service.item_code || 'Service';
                            const dept = getChargeSlipDepartmentLabel(service);
                            const amount = typeof service.amount === 'number' ? service.amount : parseFloat(service.amount || '0');
                            return '<tr>'
                                + '<td>' + escapeHtml(desc) + '</td>'
                                + '<td>' + escapeHtml(dept) + '</td>'
                                + '<td class="amount">' + formatCurrency(amount) + '</td>'
                                + '</tr>';
                        }).join('')
                        : '<tr><td colspan="3" class="text-center text-muted">No services recorded.</td></tr>';
                    chargeSlipReceiptEl.innerHTML =
                        '<div class="charge-slip-title">Charge Slip</div>'
                        + '<div class="charge-slip-field"><span>Patient:</span><span>' + escapeHtml(patientName) + '</span></div>'
                        + '<div class="charge-slip-field"><span>Queue No.:</span><span>' + escapeHtml(ticketNumber || '-') + '</span></div>'
                        + '<div class="charge-slip-field"><span>Retrieval Code:</span><span>' + escapeHtml(retrievalCode || '-') + '</span></div>'
                        + '<div class="charge-slip-separator"></div>'
                        + '<table class="charge-slip-table">'
                        + '<thead><tr><th>Service</th><th>Dept</th><th class="amount">Amount</th></tr></thead>'
                        + '<tbody>' + rows + '</tbody>'
                        + '</table>'
                        + '<div class="charge-slip-total"><span>Total</span><span>' + formatCurrency(total) + '</span></div>'
                        + '<div class="charge-slip-note">Please present this slip to the cashier.</div>';
                }

                function repeatChar(char, length) {
                    if (!length || length < 1) { return ''; }
                    return new Array(length + 1).join(char);
                }

                function truncatePlainText(value, maxLength) {
                    const safe = (value || '').toString();
                    if (safe.length <= maxLength) {
                        return safe;
                    }
                    if (maxLength <= 3) {
                        return safe.slice(0, maxLength);
                    }
                    return safe.slice(0, maxLength - 3) + '...';
                }

                function centerText(value, width) {
                    const text = truncatePlainText(value || '', width);
                    const padding = Math.max(0, width - text.length);
                    const left = Math.floor(padding / 2);
                    const right = padding - left;
                    return repeatChar(' ', left) + text + repeatChar(' ', right);
                }

                function buildChargeSlipText(details, fallback) {
                    const lineWidth = 42;
                    const separator = repeatChar('-', lineWidth);
                    const fallbackData = fallback || chargeSlipFallback || {};
                    const patientName = (details && details.patient_name) || fallbackData.patientName || 'Patient';
                    const ticketNumber = (details && (details.ticket_number || details.queue_label)) || fallbackData.queueLabel || '-';
                    const retrievalCode = (details && details.retrieval_code) || fallbackData.retrievalCode || '-';
                    const services = (details && Array.isArray(details.services)) ? details.services : [];

                    const lines = [];
                    lines.push(centerText('Charge Slip', lineWidth));
                    lines.push(separator);
                    lines.push('Patient   : ' + patientName);
                    lines.push('Queue No. : ' + ticketNumber);
                    lines.push('Retrieval : ' + retrievalCode);
                    lines.push(separator);
                    lines.push('Items:');
                    if (services.length) {
                        services.forEach(function (service) {
                            const desc = service.description || service.item_code || 'Service';
                            const dept = getChargeSlipDepartmentLabel(service);
                            lines.push(truncatePlainText(desc + ' / ' + dept, lineWidth));
                        });
                    } else {
                        lines.push('  (no services)');
                    }
                    lines.push(separator);
                    lines.push('Present this slip to the cashier.');
                    return lines.join('\n');
                }

                async function loadChargeSlipData(queueId, fallback) {
                    if (!queueId) { return null; }
                    if (chargeSlipLoadingEl) {
                        chargeSlipLoadingEl.classList.remove('d-none');
                    }
                    if (chargeSlipAlertEl) {
                        chargeSlipAlertEl.classList.add('d-none');
                        chargeSlipAlertEl.textContent = '';
                        chargeSlipAlertEl.classList.remove('alert-info');
                        chargeSlipAlertEl.classList.add('alert-danger');
                    }
                    try {
                        const params = new URLSearchParams();
                        params.append('action', 'load_payment_details');
                        params.append('payment_queue_id', queueId);
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            throw new Error(data.message || 'Unable to load charge slip.');
                        }
                        chargeSlipData = data.data || null;
                        renderChargeSlip(chargeSlipData, fallback);
                        return chargeSlipData;
                    } catch (error) {
                        chargeSlipData = null;
                        showChargeSlipError(error.message || 'Unable to load charge slip.');
                        renderChargeSlip(null, fallback);
                        return null;
                    } finally {
                        if (chargeSlipLoadingEl) {
                            chargeSlipLoadingEl.classList.add('d-none');
                        }
                    }
                }

                function openChargeSlipModal(config, fallbackArg) {
                    const options = (config && typeof config === 'object' && !Array.isArray(config))
                        ? config
                        : {
                            queueId: config,
                            fallbackData: fallbackArg || null
                        };
                    const queueId = options.queueId || null;
                    const stagedDetails = options.stagedDetails || null;
                    const pendingPayloadOption = options.pendingPayload || null;
                    const fallbackData = options.fallbackData || null;
                    const shouldRestoreTag = !!options.restoreTag;

                    if (!chargeSlipModalEl) {
                        setMessage('Unable to prepare the charge slip preview.', 'warning');
                        return;
                    }
                    if (!chargeSlipModalInstance && typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                        chargeSlipModalInstance = new bootstrap.Modal(chargeSlipModalEl);
                        chargeSlipModalEl.addEventListener('hidden.bs.modal', function () {
                            const shouldRestore = restoreTagModalOnClose;
                            resetChargeSlipModal();
                            if (shouldRestore) {
                                if (tagModalInstance) {
                                    tagModalInstance.show();
                                } else if (tagModalEl) {
                                    tagModalEl.classList.add('show');
                                }
                            }
                        });
                    }
                    resetChargeSlipModal();
                    chargeSlipQueueId = queueId;
                    chargeSlipFallback = fallbackData;
                    if (stagedDetails) {
                        chargeSlipMode = 'staged';
                        pendingTagPayload = pendingPayloadOption;
                        restoreTagModalOnClose = shouldRestoreTag;
                    }
                    if (chargeSlipCancelButton) {
                        chargeSlipCancelButton.textContent = stagedDetails ? 'Back to Tagging' : 'Close';
                    }
                    if (chargeSlipPrintButton) {
                        chargeSlipPrintButton.disabled = false;
                        chargeSlipPrintButton.textContent = stagedDetails ? 'Save & Print' : 'Print Slip';
                    }
                    if (chargeSlipModalInstance) {
                        chargeSlipModalInstance.show();
                    } else {
                        chargeSlipModalEl.classList.add('show');
                    }
                    if (stagedDetails) {
                        chargeSlipData = stagedDetails;
                        renderChargeSlip(stagedDetails, chargeSlipFallback);
                        showChargeSlipInfo('Review the details. Click Print to save and print this slip.');
                    } else if (queueId) {
                        loadChargeSlipData(queueId, chargeSlipFallback);
                    } else {
                        showChargeSlipError('Unable to prepare the charge slip preview.');
                    }
                }

                async function openChargeSlipPrintWindow() {
                    const textContent = buildChargeSlipText(chargeSlipData, chargeSlipFallback);
                    if (!textContent) {
                        setMessage('No charge slip is ready to print.', 'warning');
                        return false;
                    }
                    const styles = ''
                        + '@page { size: 80mm auto; margin: 3mm; }'
                        + 'html, body { margin: 0; padding: 0; width: 80mm; }'
                        + 'body { font-family: "Courier New", Courier, monospace; font-size: 12px; writing-mode: horizontal-tb; }'
                        + '.print-wrapper { width: 80mm; margin: 0 auto; transform: none !important; }'
                        + '.receipt-copy { padding: 6px 0 10px 0; width: 80mm; }'
                        + '.receipt-copy-label { text-align: center; font-weight: 700; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-size: 11px; }'
                        + 'pre { margin: 0; white-space: pre; }';
                    const copies = ['Customer Copy', "Cashier's Copy"];

                    function printCopy(label) {
                        return new Promise(function (resolve) {
                            const iframe = document.createElement('iframe');
                            iframe.style.position = 'fixed';
                            iframe.style.right = '0';
                            iframe.style.bottom = '0';
                            iframe.style.width = '0';
                            iframe.style.height = '0';
                            iframe.style.border = '0';
                            iframe.style.visibility = 'hidden';
                            document.body.appendChild(iframe);

                            const doc = iframe.contentDocument || iframe.contentWindow.document;
                            const content = '<div class="print-wrapper">'
                                + '<div class="receipt-copy">'
                                + '<div class="receipt-copy-label">' + escapeHtml(label) + '</div>'
                                + '<pre>' + escapeHtml(textContent) + '</pre>'
                                + '</div>'
                                + '</div>';
                            doc.open();
                            doc.write('<!DOCTYPE html><html><head><title>Charge Slip - ' + escapeHtml(label) + '</title><style>' + styles + '</style></head><body>' + content + '</body></html>');
                            doc.close();

                            const finish = function () {
                                try {
                                    iframe.contentWindow.focus();
                                    iframe.contentWindow.print();
                                } catch (error) {
                                    // ignore; print will prompt if allowed
                                }
                                setTimeout(function () {
                                    if (iframe.parentNode) {
                                        iframe.parentNode.removeChild(iframe);
                                    }
                                    resolve(true);
                                }, 300);
                            };

                            if (doc.readyState === 'complete') {
                                setTimeout(finish, 50);
                            } else {
                                iframe.onload = finish;
                            }
                        });
                    }

                    let success = true;
                    for (const label of copies) {
                        const printed = await printCopy(label);
                        success = success && printed;
                    }
                    return success;
                }

                async function handleChargeSlipPrint() {
                    if (chargeSlipPrintInProgress) {
                        return;
                    }
                    if (chargeSlipMode === 'staged') {
                        if (!pendingTagPayload) {
                            showChargeSlipError('Missing tag details. Please return to the tagging modal.');
                            return;
                        }
                        chargeSlipPrintInProgress = true;
                        if (chargeSlipPrintButton) {
                            chargeSlipPrintButton.disabled = true;
                            chargeSlipPrintButton.textContent = 'Saving...';
                        }
                        const result = await submitTagPayload(pendingTagPayload);
                        if (!result.success) {
                            showChargeSlipError(result.message || 'Unable to tag patient.');
                            chargeSlipPrintInProgress = false;
                            if (chargeSlipPrintButton) {
                                chargeSlipPrintButton.disabled = false;
                                chargeSlipPrintButton.textContent = 'Save & Print';
                            }
                            return;
                        }
                        restoreTagModalOnClose = false;
                        chargeSlipMode = 'final';
                        pendingTagPayload = null;
                        if (chargeSlipPrintButton) {
                            chargeSlipPrintButton.textContent = 'Print Slip';
                        }
                        const fallbackData = {
                            patientName: result.data && result.data.patient_name ? result.data.patient_name : (chargeSlipFallback ? chargeSlipFallback.patientName : ''),
                            queueLabel: chargeSlipFallback ? chargeSlipFallback.queueLabel : '',
                            retrievalCode: result.data && result.data.retrieval_code ? result.data.retrieval_code : (chargeSlipFallback ? chargeSlipFallback.retrievalCode : '')
                        };
                        chargeSlipFallback = fallbackData;
                        const loaded = await loadChargeSlipData(chargeSlipQueueId, chargeSlipFallback);
                        chargeSlipPrintInProgress = false;
                        if (chargeSlipPrintButton) {
                            chargeSlipPrintButton.disabled = false;
                        }
                        if (!loaded) {
                            showChargeSlipError('Charge slip saved, but unable to load the final preview. Please try again.');
                            return;
                        }
                        if (!openChargeSlipPrintWindow()) {
                            return;
                        }
                        if (chargeSlipModalInstance) {
                            chargeSlipModalInstance.hide();
                        } else if (chargeSlipModalEl) {
                            chargeSlipModalEl.classList.remove('show');
                        }
                        return;
                    }
                    const printed = openChargeSlipPrintWindow();
                    if (printed) {
                        if (chargeSlipModalInstance) {
                            chargeSlipModalInstance.hide();
                        } else if (chargeSlipModalEl) {
                            chargeSlipModalEl.classList.remove('show');
                        }
                    }
                }

                async function loadPaymentDetails(queueId, departmentQueueId) {
                    if (!queueId) { return; }
                    if (paymentServicesBody) {
                        paymentServicesBody.innerHTML = '<tr><td colspan="2" class="text-center text-muted">Loading...</td></tr>';
                    }
                    const params = new URLSearchParams();
                    params.append('action', 'load_payment_details');
                    params.append('payment_queue_id', queueId);
                    if (departmentQueueId) {
                        params.append('payment_department_queue_id', departmentQueueId);
                    }
                    try {
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            if (paymentAlertEl) {
                                paymentAlertEl.textContent = data.message || 'Unable to load payment details.';
                                paymentAlertEl.classList.remove('d-none');
                            }
                            return;
                        }
                        paymentDetails = data.data || null;
                        populatePaymentDetails(paymentDetails);
                        paymentModalQueueId = queueId;
                        paymentModalDepartmentQueueId = departmentQueueId || 0;
                    } catch (error) {
                        if (paymentAlertEl) {
                            paymentAlertEl.textContent = 'Unable to load payment details. Please try again.';
                            paymentAlertEl.classList.remove('d-none');
                        }
                    }
                }

                async function submitPayment() {
                    if (!paymentModalQueueId) {
                        if (paymentAlertEl) {
                            paymentAlertEl.textContent = 'No ticket selected.';
                            paymentAlertEl.classList.remove('d-none');
                        }
                        return;
                    }
                    const amountValue = paymentReceivedInput ? parseFloat(paymentReceivedInput.value || '0') : 0;
                    if (Number.isNaN(amountValue) || amountValue < 0) {
                        if (paymentAlertEl) {
                            paymentAlertEl.textContent = 'Enter a valid received amount.';
                            paymentAlertEl.classList.remove('d-none');
                        }
                        return;
                    }
                    const receiptNo = paymentReceiptInput ? (paymentReceiptInput.value || '').trim() : '';

                    const params = new URLSearchParams();
                    params.append('action', 'submit_payment');
                    params.append('payment_queue_id', paymentModalQueueId);
                    if (paymentModalDepartmentQueueId) {
                        params.append('payment_department_queue_id', paymentModalDepartmentQueueId);
                    }
                    params.append('amount_received', amountValue.toString());
                    if (receiptNo) {
                        params.append('receipt_no', receiptNo);
                    }

                    try {
                        if (paymentSubmitButton) {
                            paymentSubmitButton.disabled = true;
                        }
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            if (paymentAlertEl) {
                                paymentAlertEl.textContent = data.message || 'Unable to record payment.';
                                paymentAlertEl.classList.remove('d-none');
                            }
                            if (paymentSubmitButton) {
                                paymentSubmitButton.disabled = false;
                            }
                            return;
                        }
                        if (paymentModalInstance) {
                            paymentModalInstance.hide();
                        }
                        setMessage(data.message || 'Payment recorded.', 'success');
                        await fetchQueues(false);
                    } catch (error) {
                        if (paymentAlertEl) {
                            paymentAlertEl.textContent = 'Payment submission failed. Please try again.';
                            paymentAlertEl.classList.remove('d-none');
                        }
                        if (paymentSubmitButton) {
                            paymentSubmitButton.disabled = false;
                        }
                    }
                }

                function openPaymentModal(queueId, departmentQueueId) {
                    resetPaymentModal(true);
                    if (!queueId || !paymentModalEl) {
                        setMessage('Unable to load payment details for this ticket.', 'warning');
                        return;
                    }
                    if (!paymentModalInstance) {
                        paymentModalInstance = new bootstrap.Modal(paymentModalEl);
                    }
                    paymentModalQueueId = queueId;
                    paymentModalDepartmentQueueId = departmentQueueId || 0;
                    paymentModalInstance.show();
                    loadPaymentDetails(queueId, departmentQueueId);
                }

                function getSelectedValues(selectEl) {
                    if (!selectEl || !selectEl.selectedOptions) {
                        return [];
                    }
                    return Array.from(selectEl.selectedOptions).map(function (option) {
                        return option.value;
                    });
                }

                function updateButtonStates() {
                    const hasWaitingSelected = getSelectedValues(waitingSelect).length > 0;
                    const hasActiveSelected = selectedActiveIds.length > 0;
                    if (callButton) {
                        callButton.disabled = !hasWaitingSelected;
                    }
                    if (completeButton) {
                        completeButton.disabled = !hasActiveSelected;
                    }
                    if (returnButton) {
                        returnButton.disabled = !hasActiveSelected;
                    }
                }

                async function fetchQueues(showNotification) {
                    try {
                        const response = await fetch('queue_board_data.php?_=' + Date.now(), {cache: 'no-store'});
                        const data = await response.json();
                        if (!data.success) {
                          setMessage(data.message || 'Unable to load queue.', 'danger');
                          return;
                      }
                      if (registrationLabel) {
                          if (data.registration && data.registration.number) {
                              registrationLabel.textContent = 'Registration ' + data.registration.number;
                          } else if (data.registration && !data.registration.number) {
                              registrationLabel.textContent = 'Registration not assigned';
                          }
                      }
                      if (Array.isArray(data.service_options)) {
                          const preserveSelection = !!(tagModalEl && tagModalEl.classList.contains('show'));
                          syncServiceOptions(data.service_options, preserveSelection);
                      } else if (!serviceOptions.length) {
                          syncServiceOptions([], true);
                      }
                      populateSelect(waitingSelect, data.waiting || [], 'id', 'label');
                      renderActiveList(data.active || []);
                      finishedPatients = Array.isArray(data.finished) ? data.finished : [];
                      const finishedIds = new Set(finishedPatients.map(function (f) { return String(f.queue_id || ''); }));
                      const routedItems = (data.routed_active || []).filter(function (item) {
                          return item && !finishedIds.has(String(item.queue_id || ''));
                      });
                      renderRoutedList(routedItems);
                      cashierDepartmentId = data.cashier_department_id || null;
                      renderDepartmentQueues(data.departments || []);
                      renderFinishedPatients(finishedPatients);
                        updateButtonStates();
                        const currentMessageType = messageBox ? String(messageBox.dataset.messageType || '').toLowerCase() : '';
                        if (data.message) {
                            setMessage(data.message, data.message_type || 'info');
                        } else if (showNotification || (currentMessageType && currentMessageType !== 'warning' && currentMessageType !== 'danger')) {
                            setMessage('');
                        }
                    } catch (error) {
                        setMessage('Failed to communicate with the server.', 'danger');
                    }
                }

                async function submitAction(action, ids, extraPayload) {
                    if (!ids.length) {
                        return;
                    }
                    const params = new URLSearchParams();
                    params.append('action', action);
                    ids.forEach(function (id) {
                        params.append('ids[]', id);
                    });
                    if (extraPayload && typeof extraPayload === 'object') {
                        Object.keys(extraPayload).forEach(function (key) {
                            const value = extraPayload[key];
                            if (value !== undefined && value !== null && value !== '') {
                                params.append(key, value);
                            }
                        });
                    }

                    try {
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            setMessage(data.message || 'Unable to update queue.', 'danger');
                        } else {
                            setMessage(data.message || 'Queue updated.', 'success');
                            await fetchQueues(false);
                        }
                    } catch (error) {
                        setMessage('Action failed. Please try again.', 'danger');
                    }
                }

                async function submitDepartmentCall(departmentId, queueRecordId) {
                    if (!departmentId) {
                        setMessage('Please select a department.', 'warning');
                        return;
                    }

                    const params = new URLSearchParams();
                    params.append('action', 'department_call');
                    params.append('department_id', departmentId);
                    if (queueRecordId) {
                        params.append('department_queue_id', queueRecordId);
                    }

                    try {
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            setMessage(data.message || 'Unable to trigger department announcement.', 'danger');
                        } else {
                            setMessage(data.message || 'Department announcement triggered.', 'success');
                            await fetchQueues(false);
                        }
                    } catch (error) {
                        setMessage('Department action failed. Please try again.', 'danger');
                    }
                }

                async function submitDepartmentComplete(departmentId, queueRecordId) {
                    if (!departmentId || !queueRecordId) {
                        setMessage('Select a ticket to complete.', 'warning');
                        return;
                    }

                    const params = new URLSearchParams();
                    params.append('action', 'department_complete');
                    params.append('department_id', departmentId);
                    params.append('department_queue_id', queueRecordId);

                    try {
                        const response = await fetch('queue_board_action.php', {
                            method: 'POST',
                            body: params,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            setMessage(data.message || 'Unable to update department ticket.', 'danger');
                        } else {
                            setMessage(data.message || 'Department ticket updated.', 'success');
                            await fetchQueues(false);
                        }
                    } catch (error) {
                        setMessage('Department update failed. Please try again.', 'danger');
                    }
                }

                function submitDepartmentFlag(departmentId, queueRecordId) {
                    return submitDepartmentComplete(departmentId, queueRecordId);
                }

                async function handleUploadPreview() {
                    if (!uploadFileInput || !uploadFileInput.files.length) {
                        setUploadAlert('error', 'Please choose an exported HTML file first.');
                        return;
                    }
                    const file = uploadFileInput.files[0];
                    const formData = new FormData();
                    formData.append('action', 'preview');
                    formData.append('patient_file', file);

                    setUploadAlert('', '');
                    setUploadLoading(true);

                    try {
                        const response = await fetch('tag_patient_upload.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            setUploadAlert('error', data.message || 'Unable to generate preview.');
                            return;
                        }
                        uploadPreviewRows = Array.isArray(data.rows) ? data.rows : [];
                        renderUploadPreview(uploadPreviewRows);
                        if (uploadImportButton) {
                            uploadImportButton.disabled = !uploadPreviewRows.length;
                        }
                        if (Array.isArray(data.warnings) && data.warnings.length) {
                            setUploadAlert('warning', data.warnings.join(' '));
                        }
                    } catch (error) {
                        setUploadAlert('error', 'Upload failed. Please try again.');
                    } finally {
                        setUploadLoading(false);
                    }
                }

                async function handleUploadImport() {
                    if (!uploadPreviewRows.length) {
                        setUploadAlert('error', 'Generate a preview before importing.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'import');

                    setUploadAlert('', '');
                    setUploadLoading(true);

                    try {
                        const response = await fetch('tag_patient_upload.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        const data = await response.json();
                        if (!data.success) {
                            setUploadAlert('error', data.message || 'Import failed.');
                            return;
                        }
                        if (uploadImportButton) {
                            uploadImportButton.disabled = true;
                        }
                        if (uploadPreviewCount && data.summary) {
                            uploadPreviewCount.textContent = 'Processed ' + (data.summary.processed || 0) +
                                ' - inserted ' + (data.summary.inserted || 0) +
                                ', updated ' + (data.summary.updated || 0) +
                                ', skipped ' + (data.summary.skipped || 0) + '.';
                        }
                        if (Array.isArray(data.warnings) && data.warnings.length) {
                            setUploadAlert('warning', data.warnings.join(' '));
                        } else {
                            setUploadAlert('', '');
                        }
                        setMessage(data.message || 'Patient import completed.', 'success');
                    } catch (error) {
                        setUploadAlert('error', 'Import failed. Please try again.');
                    } finally {
                        setUploadLoading(false);
                    }
                }

                if (waitingSelect) {
                    waitingSelect.addEventListener('change', updateButtonStates);
                }
                if (nowServingPrevButton) {
                    nowServingPrevButton.addEventListener('click', function () {
                        if (nowServingPage > 1) {
                            nowServingPage--;
                            renderNowServingPage();
                        }
                    });
                }
                if (nowServingNextButton) {
                    nowServingNextButton.addEventListener('click', function () {
                        const totalPages = Math.max(1, Math.ceil(nowServingData.length / nowServingPageSize));
                        if (nowServingPage < totalPages) {
                            nowServingPage++;
                            renderNowServingPage();
                        }
                    });
                }
                if (callButton) {
                    callButton.addEventListener('click', function () {
                        const ids = getSelectedValues(waitingSelect);
                        submitAction('call', ids);
                    });
                }
                if (completeButton) {
                    completeButton.addEventListener('click', function () {
                        submitAction('complete', selectedActiveIds.slice());
                    });
                }
                if (returnButton) {
                    returnButton.addEventListener('click', function () {
                        submitAction('return', selectedActiveIds.slice());
                    });
                }
                if (refreshButton) {
                    refreshButton.addEventListener('click', function () {
                        fetchQueues(false);
                    });
                }
                if (paymentSubmitButton) {
                    paymentSubmitButton.addEventListener('click', submitPayment);
                }
                if (paymentModalEl) {
                    paymentModalEl.addEventListener('hidden.bs.modal', function () {
                        resetPaymentModal(true);
                    });
                }
                if (chargeSlipPrintButton) {
                    chargeSlipPrintButton.addEventListener('click', handleChargeSlipPrint);
                }
                if (uploadOpenButton && uploadModalEl) {
                    uploadOpenButton.addEventListener('click', function () {
                        if (!uploadModalInstance) {
                            uploadModalInstance = new bootstrap.Modal(uploadModalEl);
                        }
                        resetUploadModal(true);
                        uploadModalInstance.show();
                    });
                }
                if (uploadModalEl) {
                    uploadModalEl.addEventListener('hidden.bs.modal', function () {
                        resetUploadModal(true);
                    });
                }
                if (uploadTriggerButton && uploadFileInput) {
                    uploadTriggerButton.addEventListener('click', function () {
                        uploadFileInput.click();
                    });
                }
                if (uploadFileInput) {
                    uploadFileInput.addEventListener('change', function () {
                        if (uploadFilename) {
                            uploadFilename.textContent = uploadFileInput.files.length
                                ? uploadFileInput.files[0].name
                                : 'No file selected';
                        }
                    });
                }
                if (uploadPreviewButton) {
                    uploadPreviewButton.addEventListener('click', handleUploadPreview);
                }
                if (uploadImportButton) {
                    uploadImportButton.addEventListener('click', handleUploadImport);
                }
                if (uploadResetButton) {
                    uploadResetButton.addEventListener('click', function () {
                        resetUploadModal(true);
                    });
                }
                if (departmentQueuesContainer) {
                    departmentQueuesContainer.addEventListener('click', function (event) {
                        const paginationBtn = event.target.closest('.dept-page-prev, .dept-page-next');
                        if (paginationBtn) {
                            const deptId = paginationBtn.dataset.departmentId || '';
                            if (!deptId) { return; }
                            const delta = paginationBtn.classList.contains('dept-page-prev') ? -1 : 1;
                            const currentPage = departmentPages[deptId] || 1;
                            departmentPages[deptId] = Math.max(1, currentPage + delta);
                            renderDepartmentQueues(departmentData);
                            return;
                        }

                        const actionButton = event.target.closest('.department-ticket-action');
                        if (!actionButton) { return; }
                        const deptId = parseInt(actionButton.dataset.departmentId || '0', 10);
                        const recordId = parseInt(actionButton.dataset.recordId || '0', 10);
                        if (!deptId || !recordId) { return; }

                        if (actionButton.classList.contains('action-call')) {
                            submitDepartmentCall(deptId, recordId);
                            return;
                        }

                        if (actionButton.classList.contains('action-flag')) {
                            submitDepartmentFlag(deptId, recordId);
                            return;
                        }

                        if (actionButton.classList.contains('action-pay')) {
                            const queueId = parseInt(actionButton.dataset.queueId || '0', 10);
                            openPaymentModal(queueId, recordId);
                            return;
                        }
                    });
                }

                if (nowServingBody) {
                    nowServingBody.addEventListener('click', function (event) {
                        const callBtn = event.target.closest('.queue-routed-call');
                        if (!callBtn) { return; }
                        const callId = callBtn.dataset.callId;
                        if (callId) {
                            submitAction('announce', [callId]);
                        }
                    });
                }

                if (activeContainer) {
                    activeContainer.addEventListener('click', function (event) {
                        const tagBtn = event.target.closest('.queue-tag-btn');
                        if (tagBtn) {
                            event.stopPropagation();
                            const queueIdValue = tagBtn.dataset.queueId || '';
                            const callIdValue = tagBtn.dataset.callId || '';
                            const ticketLabel = tagBtn.dataset.ticket || 'Ticket';
                            const currentCode = tagBtn.dataset.currentCode || '';
                            if (queueIdValue) {
                                openTagPatientModal({
                                    queueId: queueIdValue,
                                    callId: callIdValue,
                                    ticket: ticketLabel,
                                    retrievalCode: currentCode
                                });
                            } else {
                                setMessage('Unable to tag this queue ticket.', 'warning');
                            }
                            return;
                        }

                        const announceBtn = event.target.closest('.announce-btn');
                        if (announceBtn) {
                            event.stopPropagation();
                            const callId = announceBtn.dataset.callId;
                            if (callId) {
                                submitAction('announce', [callId]);
                            }
                            return;
                        }

                        const item = event.target.closest('.queue-active-item');
                        if (!item) { return; }
                        const callId = item.dataset.callId;
                        if (!callId) { return; }

                        const index = selectedActiveIds.indexOf(callId);
                        if (index >= 0) {
                            selectedActiveIds.splice(index, 1);
                            item.classList.remove('active');
                        } else {
                            selectedActiveIds.push(callId);
                            item.classList.add('active');
                        }
                        updateButtonStates();
                    });
                }

                fetchQueues(false);
                refreshTimer = setInterval(fetchQueues, 5000);

                window.addEventListener('beforeunload', function () {
                    if (refreshTimer) {
                        clearInterval(refreshTimer);
                    }
                });
            })();
