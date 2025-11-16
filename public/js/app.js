/*************************************************************
 *  SEAT — Simulator Engineering Authoring Tool
 *  Consolidated JavaScript (Part 1 of 2)
 *  All inline JavaScript removed from index.html
 *  This file runs immediately on load (Option 1)
 *************************************************************/

/* -----------------------------------------------------------
   TinyMCE: Disable stats (must run immediately)
------------------------------------------------------------*/
(function () {
    if (window.tinymce) {
        window.tinymce.stateless = true;
        console.log("TinyMCE stats disabled globally");
    }
})();

/* -----------------------------------------------------------
   Helper: HTML escape (same function you had)
------------------------------------------------------------*/
function h(s) {
    if (s === null || s === undefined) return "";
    return String(s)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

/* -----------------------------------------------------------
   Auto-resize (instructions, justification)
------------------------------------------------------------*/
function autoResizeTextarea(el) {
    if (!el) return;
    el.style.height = "auto";
    el.style.height = el.scrollHeight + "px";
}

/* -----------------------------------------------------------
   Attachment Loader (moved exactly as-is)
------------------------------------------------------------*/
function loadExistingAttachments(attachments) {
    $("input[name='keep_attachments[]']").remove();  // remove old ones

    const $c = $("#existingAttachments").empty();
    if (!attachments || !attachments.length) {
        $c.html('<small class="text-muted">No attachments</small>');
        return;
    }

    const list = attachments.map(url => {
        const name = url.split("/").pop();
        const full = url.startsWith("http") ? url : location.origin + url;

        const escAttr = url.replace(/&/g, "&amp;")
                           .replace(/"/g, "&quot;")
                           .replace(/'/g, "&#39;");
        const escJS = url.replace(/\\/g, "\\\\").replace(/'/g, "\\'");

        return `
            <div class="d-flex align-items-center mb-1 p-2 border rounded bg-light">
                <a href="${full}" target="_blank"
                   class="text-decoration-none me-2 flex-grow-1">${name}</a>
                <button type="button" class="btn btn-sm btn-outline-danger"
                    onclick="removeExistingAttachment(this, '${escJS}')">Remove</button>
                <input type="hidden" name="keep_attachments[]" value="${escAttr}">
            </div>`;
    }).join("");

    $c.html(`
        <div class="border p-2 rounded bg-white mb-2">
            <small class="text-muted d-block mb-2"><strong>Current attachments:</strong></small>
            ${list}
        </div>
    `);
}

function removeExistingAttachment(btn, url) {
    if (!confirm("Remove this attachment?")) return;
    $(btn).closest("div").remove();
}

/* -----------------------------------------------------------
   Fleet → Device Map (unchanged)
------------------------------------------------------------*/
const fleetDeviceMap = {
    '737': ['FFS-2', 'FFS-3', 'FFS-4', 'FFS-5', 'FFS-6', 'FTD-2', 'FTD-3', 'FTD-4', 'FFS-7', 'FFS-8', 'FFS-9', 'FFS-10'],
    '737-MAX': ['FFS-1', 'FFS-2', 'FFS-3', 'FFS-4', 'FFS-5', 'FFS-6', 'FFS-7', 'FFS-8', 'FFS-9', 'FFS-10', 'FFS-11',
        'FTD-1', 'FTD-2', 'FTD-3', 'FTD-4', 'FTD-5', 'FTD-6', 'FTD-7', 'FTD-8', 'FTD-9', 'FTD-10', 'FTD-11'],
    '757': ['FFS-1', 'FFS-2', 'FFS-3', 'FTD-1', 'FTD-2'],
    '767': ['FFS-3', 'FFS-4', 'FFS-5'],
    '777': ['FFS-1', 'FFS-2', 'FFS-3', 'FFS-5', 'FTD-1', 'FTD-2', 'FFS-6', 'FFS-7', 'FTD-3'],
    '787': ['FFS-1', 'FFS-2', 'FFS-3', 'FFS-4', 'FFS-5', 'FFS-6', 'FFS-7', 'FFS-8', 'FFS-9',
        'FTD-1', 'FTD-2', 'FTD-3', 'FTD-4', 'FTD-5', 'FTD-6'],
    'A320': ['FFS-1', 'FFS-4', 'FTD-1', 'FTD-2', 'FFS-6', 'FFS-7', 'FFS-8', 'FFS-9', 'FFS-10',
        'FFS-11', 'FFS-12', 'FFS-13', 'FTD-3', 'FTD-4', 'FTD-5', 'FTD-6']
};

/* -----------------------------------------------------------
   STARTUP: jQuery ready wrapper
------------------------------------------------------------*/
$(document).ready(function () {

    /***********************************************************
     *  SELECT2 INITIALIZATION
     ***********************************************************/
    $("#fleet").select2({ placeholder: "Select Fleet", allowClear: true });
    $("#device").select2({ placeholder: "Select Device(s)", allowClear: true });

    $("#justification").on("input", function () {
        autoResizeTextarea(this);
    });

    /* -------------------------------------------------------
       Fleet → Device population logic
    -------------------------------------------------------*/
    $("#fleet").on("change", function () {
        const fleet = $(this).val();
        const $device = $("#device");

        $device.empty().trigger("change");
        $("#deviceCol").toggle(!!fleet);

        if (fleet && fleetDeviceMap[fleet]) {
            fleetDeviceMap[fleet].forEach(d =>
                $device.append(`<option value="${d}">${d}</option>`)
            );
        }
    });

    /***********************************************************
     *  GLOBAL ADD PART ROW FUNCTION
     ***********************************************************/
    window.addPartRow = function (data = {}) {
        const row = `
        <tr>
            <td><input type="text" class="form-control form-control-sm part-input"
                       value="${h(data.part || '')}"></td>

            <td><input type="text" class="form-control form-control-sm desc-input"
                       value="${h(data.desc || '')}"></td>

            <td>
              <select class="form-control form-control-sm type-select">
                <option value="add" ${data.type === "add" ? "selected" : ""}>Add</option>
                <option value="remove" ${data.type === "remove" ? "selected" : ""}>Remove</option>
                <option value="modify" ${data.type === "modify" ? "selected" : ""}>Modify</option>
              </select>
            </td>

            <td><input type="number" class="form-control form-control-sm qty-input"
                       min="1" value="${h(data.qty || '')}"></td>

            <td>
                <button type="button" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;

        $("#partsTable tbody").append(row);
    };

    /***********************************************************
     *  REMOVE PART ROW
     ***********************************************************/
    $(document).on("click", "#partsTable .btn-outline-danger", function () {
        $(this).closest("tr").remove();
    });

    /***********************************************************
     *  GLOBAL ADD INSTRUCTION ROW
     ***********************************************************/
    window.addInstrRow = function (data = {}) {
        const idx = $("#instrTable tbody tr").length;

        const row = `
        <tr>
            <td class="text-center align-middle">${idx + 1}</td>

            <td>
                <textarea class="form-control instr-text"
                    name="instructions[${idx}][instruction]"
                    placeholder="Enter instruction..."
                    rows="1">${(data.instruction || '')
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")}
                </textarea>
            </td>

            <td>
                <input type="text" class="form-control instr-notes"
                    name="instructions[${idx}][notes]"
                    value="${h(data.notes || '')}"
                    placeholder="Notes">
            </td>

            <td class="text-center align-middle">
                <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    onclick="removeInstrRow(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>`;

        $("#instrTable tbody").append(row);

        /* TinyMCE initialize for this new row only */
        tinymce.init({
            selector: `#instrTable tbody tr:last-child .instr-text`,
            height: 80,
            menubar: false,
            plugins: "lists link image table code autoresize",
            toolbar: "undo redo | bold italic underline | bullist numlist | link image | removeformat | code | table",
            branding: false,
            statusbar: true,
            resize: true,
            autoresize_bottom_margin: 12,
            stateless: true,

            setup: ed => {
                ed.on("change keyup", () => {
                    ed.save();
                    try { autoResizeTextarea(ed.getElement()); } catch (e) {}
                });
            },

            file_picker_types: "image",
            file_picker_callback: (callback, value, meta) => {
                let input = document.createElement("input");
                input.type = "file";
                input.accept = "image/*";

                input.onchange = function () {
                    const file = this.files[0];
                    const reader = new FileReader();
                    reader.onload = () => {
                        callback(reader.result, { alt: file.name });
                    };
                    reader.readAsDataURL(file);
                };

                input.click();
            }
        });
    };

    /***********************************************************
     *  REMOVE INSTRUCTION ROW
     ***********************************************************/
    window.removeInstrRow = function (btn) {
        const $row = $(btn).closest("tr");
        const textarea = $row.find(".instr-text");
        const id = textarea.attr("id");

        // remove TinyMCE instance
        const editor = tinymce.get(id);
        if (editor) editor.remove();

        $row.remove();
        renumberInstr();
    };

    function renumberInstr() {
        $("#instrTable tbody tr").each(function (i) {
            $(this).find("td:first").text(i + 1);

            const $txt = $(this).find(".instr-text");
            const $notes = $(this).find(".instr-notes");

            $txt.attr("name", `instructions[${i}][instruction]`);
            $notes.attr("name", `instructions[${i}][notes]`);
        });
    }

});  // end document.ready


/*************************************************************
 *  js/app.js — PART 3 (Final Section)
 *************************************************************/

/* -----------------------------------------------------------
   FORM SUBMIT HANDLING (AJAX)
------------------------------------------------------------*/

$(document).ready(function () {

    $("#seaForm").on("submit", function (e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $("#submitBtn");

        $btn.prop("disabled", true).text("Saving...");
        $(".alert").remove();

        const formData = new FormData(this);

        /* ----- Collect Parts ----- */
        getParts().forEach((p, i) => {
            formData.append(`parts[${i}][part]`, p.part);
            formData.append(`parts[${i}][desc]`, p.desc);
            formData.append(`parts[${i}][type]`, p.type);
            formData.append(`parts[${i}][qty]`, p.qty);
        });

        /* ----- Collect Instructions ----- */
        getInstructions().forEach((instr, i) => {
            formData.append(`instructions[${i}][instruction]`, instr.instruction);
            formData.append(`instructions[${i}][notes]`, instr.notes);
        });

        /* ----- Existing Attachments for Updates ----- */
        if ($("#sea_action").val() === "update") {
            $("#existingAttachments input[name='keep_attachments[]']").each(function () {
                formData.append("keep_attachments[]", this.value);
            });
        }

        $.ajax({
            url: "./src/submit.php",
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            cache: false,
            headers: { "X-Requested-With": "XMLHttpRequest" },

            success: function (res) {
                if (res.conflict) {
                    const $list = $("#conflictList").empty();
                    res.conflicting_files.forEach(f => $list.append(`<li>${f}</li>`));

                    $("#confirmOverwrite").off("click").on("click", function () {
                        res.conflicting_files.forEach(f => formData.append("overwrite[]", f));

                        $("#overwriteModal").modal("hide");
                        $btn.prop("disabled", true).text("Overwriting...");

                        $.ajax({
                            url: "./src/submit.php",
                            method: "POST",
                            data: formData,
                            processData: false,
                            contentType: false,
                            dataType: "json",
                            cache: false,
                            headers: { "X-Requested-With": "XMLHttpRequest" },
                            success: res => handleSuccess(res, $btn),
                            error: xhr => handleError(xhr, $btn)
                        });
                    });

                    $("#overwriteModal").modal("show");
                } else {
                    handleSuccess(res, $btn);
                }
            },

            error: function (xhr) {
                handleError(xhr, $btn);
            }
        });
    });

}); // end submit handler



/* -----------------------------------------------------------
   SUCCESS / ERROR HANDLERS
------------------------------------------------------------*/

function handleSuccess(res, $btn) {
    if (res.success) {
        showAlert("success", res.message);
        $("html, body").animate({ scrollTop: 0 }, 300);

        if ($("#sea_action").val() === "create") {
            const newId = res.sea_id;

            $("#sea_id").val(newId);
            $("#display_id").val(newId);
            $("#sea_action").val("update");

            $("#formTitle").text("Edit SEA – " + newId);
            $("#submitBtn").text("Update SEA");

            history.replaceState(null, null, "?id=" + newId);
        }

        loadExistingAttachments(res.attachments || []);
    } else {
        showAlert("danger", res.message || "Unknown error");
    }

    $btn.prop("disabled", false).text(
        $("#sea_action").val() === "create" ? "Create SEA" : "Update SEA"
    );
}

function handleError(xhr, $btn) {
    let msg = "Network error";

    if (xhr.responseJSON?.message) {
        msg = xhr.responseJSON.message;
    } else if (xhr.status === 406) {
        msg = "Request blocked (406). Check file size or mod_security.";
    } else if (xhr.status === 500) {
        msg = "Server error. Check logs.";
    }

    showAlert("danger", msg);
    console.error("AJAX Error:", xhr);

    $btn.prop("disabled", false).text(
        $("#sea_action").val() === "create" ? "Create SEA" : "Update SEA"
    );
}



/* -----------------------------------------------------------
   SHOW ALERT
------------------------------------------------------------*/
function showAlert(type, message) {
    const alert = `
        <div class="alert alert-${type} alert-dismissible fade show mt-3" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    $("#createForm").prepend(alert);

    $("html, body").animate({ scrollTop: 0 }, 300);
}



/* -----------------------------------------------------------
   PARTS / INSTRUCTIONS COLLECTION
------------------------------------------------------------*/
function getParts() {
    const parts = [];

    $("#partsTable tbody tr").each(function () {
        const row = $(this);
        parts.push({
            part: row.find(".part-input").val().trim(),
            desc: row.find(".desc-input").val().trim(),
            type: row.find(".type-select").val().trim(),
            qty: row.find(".qty-input").val().trim()
        });
    });

    return parts;
}

function getInstructions() {
    const list = [];

    $("#instrTable tbody tr").each(function () {
        const row = $(this);

        list.push({
            instruction: row.find(".instr-text").val().trim(),
            notes: row.find(".instr-notes").val().trim()
        });
    });

    return list;
}



/* -----------------------------------------------------------
   RESET THE ENTIRE FORM
------------------------------------------------------------*/
function resetForm(isCreate = false) {
    const formEl = document.getElementById("seaForm");
    if (formEl?.reset) formEl.reset();

    $("#fleet, #device, #priority, #status").val("").trigger("change");

    // Remove all TinyMCE editors
    tinymce.remove("#instrTable .instr-text");

    $("#instrTable tbody").empty();
    $("#partsTable tbody").empty();

    if (isCreate) {
        addPartRow();
    }

    $("#existingAttachments")
        .empty()
        .html('<small class="text-muted">No attachments</small>');

    $("#attachments").val("");

    $("#ea_number, #revision, #impact, #justification, #description, #requester, #target_date")
        .val("");

    $(".alert").remove();
}



/* -----------------------------------------------------------
   NAVIGATION + VIEW SWITCHING
------------------------------------------------------------*/

window.showCreateForm = function () {
    hideAll();
    resetForm(true);

    $("#createForm").removeClass("hidden");

    $("#sea_action").val("create");
    $("#formTitle").text("New SEA");
    $("#submitBtn").text("Create SEA");

    $("#sea_id").val("");
    $("#display_id").val("");

    $("#existingAttachments").html('<small class="text-muted">No attachments</small>');

    addInstrRow();
    generateIdPreview();

    $("#fleet")
        .off("change.idPreview")
        .on("change.idPreview", generateIdPreview);
};


function generateIdPreview() {
    let fleet = $("#fleet").val() || "UNKNOWN";
    fleet = fleet.toUpperCase().replace(/[^A-Z0-9]/g, "");
    if (fleet === "") fleet = "UNKNOWN";

    const today = new Date();
    const datePart = today.toISOString().slice(2, 10).replace(/-/g, "");
    const randomPart = String(Math.floor(100 + Math.random() * 900));

    const preview = `SEA-${fleet}-${datePart}-${randomPart}`;
    $("#display_id").val(preview);
}


window.showSeaList = function () {
    hideAll();
    $("#listView").removeClass("hidden");
    loadSeaList();
};

window.goBack = function () {
    hideAll();
    $("#choiceScreen").removeClass("hidden");
    history.replaceState(null, null, window.location.pathname);
};

function hideAll() {
    $("#choiceScreen, #createForm, #listView").addClass("hidden");
}



/* -----------------------------------------------------------
   LIST VIEW — LOAD EXISTING SEAs
------------------------------------------------------------*/

function loadSeaList() {
    const $c = $("#seaContainer");
    $c.html('<div class="text-center py-5"><div class="spinner-border"></div> Loading...</div>');

    fetch("./src/list_seas.php")
        .then(r => {
            if (!r.ok) throw new Error("HTTP " + r.status);
            return r.text();
        })
        .then(html => {
            $c.html(html);

            $("#fleetFilter").html('<option value="">All Fleets</option>');
            const fleets = new Set();

            $(".sea-card").each(function () {
                const $card = $(this);
                const $fleetStrong = $card.find("strong").filter((_, el) => $(el).text().trim() === "Fleet:");

                if ($fleetStrong.length) {
                    let fleetVal = "";
                    const next = $fleetStrong[0].nextSibling;

                    if (next && next.nodeType === Node.TEXT_NODE) {
                        fleetVal = next.textContent.split("|")[0].trim();
                    } else {
                        const match = $fleetStrong.parent().text().match(/Fleet:\s*([^\|]+)/i);
                        fleetVal = match ? match[1].trim() : "";
                    }

                    if (fleetVal) fleets.add(fleetVal);
                }
            });

            Array.from(fleets).sort().forEach(f => {
                $("#fleetFilter").append(`<option value="${f}">${f}</option>`);
            });

            filterList();
        })
        .catch(err => {
            $c.html(`<div class="alert alert-danger">Failed: ${err.message}</div>`);
        });
}


/* -----------------------------------------------------------
   LIST FILTERING (Search + Fleet)
------------------------------------------------------------*/
function filterList() {
    const term = $("#searchInput").val().toLowerCase().trim();
    const selectedFleet = $("#fleetFilter").val();

    $(".sea-card").each(function () {
        const $card = $(this);
        const $col = $card.closest(".col-md-6");

        const fullText = $card.text().toLowerCase();
        const matchesSearch = !term || fullText.includes(term);

        let cardFleet = "";
        const $fleetStrong = $card.find("strong").filter(function () {
            return $(this).text().trim() === "Fleet:";
        });

        if ($fleetStrong.length) {
            const fleetNode = $fleetStrong[0].nextSibling;

            if (fleetNode && fleetNode.nodeType === Node.TEXT_NODE) {
                cardFleet = fleetNode.textContent.split("|")[0].trim();
            } else {
                const match = $fleetStrong.parent().text().match(/Fleet:\s*([^\|]+)/i);
                cardFleet = match ? match[1].trim() : "";
            }
        }

        const matchesFleet = !selectedFleet || cardFleet === selectedFleet;
        $col.toggle(matchesSearch && matchesFleet);
    });
}

$("#searchInput").on("input", filterList);
$("#fleetFilter").on("change", filterList);



/* -----------------------------------------------------------
   DELETE SEA
------------------------------------------------------------*/
window.deleteSea = function (id) {
    if (!confirm(`Delete SEA-${id}? This cannot be undone.`)) return;

    const f = document.createElement("form");
    f.method = "POST";
    f.action = "./src/delete.php";
    f.style.display = "none";

    const i = document.createElement("input");
    i.type = "hidden";
    i.name = "filename";
    i.value = `sea-${id}.json`;

    f.appendChild(i);
    document.body.appendChild(f);
    f.submit();
};



/* -----------------------------------------------------------
   EDIT SEA — LOAD JSON AND POPULATE FORM
------------------------------------------------------------*/
window.editSea = function (id) {
    hideAll();

    $("#sea_action").val("update");
    $("#formTitle").text("Edit SEA – " + id);
    $("#submitBtn").text("Update SEA");

    $("#seaForm")[0].reset();
    $("#partsTable tbody").empty();
    $("#instrTable tbody").empty();

    $("#fleet").val("").trigger("change");
    $("#device").val(null).trigger("change");

    $("#sea_id").val(id);
    $("#display_id").val(id);

    resetForm(false);

    fetch(`data/sea-${id}.json`)
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(d => {

            $("#ea_number").val(d.ea_number || "");
            $("#revision").val(d.revision || "");
            $("#requester").val(d.requester || "");
            $("#description").val(d.description || "");
            $("#justification").val(d.justification || "");
            $("#impact").val(d.impact || "");

            $("#priority").val(d.priority || "Medium");
            $("#target_date").val(d.target_date || "");
            $("#status").val(d.status || "Planning");

            (JSON.parse(d.parts_json || "[]") || []).forEach(addPartRow);
            (JSON.parse(d.instructions_json || "[]") || []).forEach(addInstrRow);

            setTimeout(() => {
                tinymce.remove("#instrTable .instr-text");

                tinymce.init({
                    selector: "#instrTable .instr-text",
                    height: 80,
                    menubar: false,
                    plugins: "lists link image table code autoresize",
                    toolbar: "undo redo | bold italic underline | bullist numlist | link image | removeformat | code | table",
                    branding: false,
                    statusbar: true,
                    resize: true,
                    autoresize_bottom_margin: 12,
                    stateless: true,
                    setup: ed => {
                        ed.on("change keyup", () => {
                            ed.save();
                            try { autoResizeTextarea(ed.getElement()); } catch (e) {}
                        });
                    },

                    file_picker_types: "image",
                    file_picker_callback: function (callback) {
                        let input = document.createElement("input");
                        input.type = "file";
                        input.accept = "image/*";

                        input.onchange = function () {
                            let file = this.files[0];
                            let reader = new FileReader();

                            reader.onload = function () {
                                callback(reader.result, { alt: file.name });
                            };

                            reader.readAsDataURL(file);
                        };

                        input.click();
                    }
                });

                $("#instrTable .instr-text").each(function () {
                    autoResizeTextarea(this);
                });

            }, 150);

            loadExistingAttachments(d.attachments || []);
            $("#fleet").val(d.fleet || "").trigger("change");

            const devices = Array.isArray(d.device)
                ? d.device
                : (d.device ? [d.device] : []);

            $("#device").val(devices).trigger("change");

            $("#createForm").removeClass("hidden");
        })
        .catch(err => {
            console.error(err);
            showAlert("danger", "Failed to load SEA: " + err.message);
            $("#choiceScreen").removeClass("hidden");
        });
};



/* -----------------------------------------------------------
   STARTUP LOGIC
------------------------------------------------------------*/
$(document).ready(function () {

    hideAll();
    $("#choiceScreen").removeClass("hidden");

    /* Load SEA if URL contains ?id=xxxx */
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get("id");
    if (editId) editSea(editId);

    /* Expand / collapse description text */
    document.addEventListener("click", function (e) {
        if (e.target.classList.contains("expand-desc")) {
            e.preventDefault();

            const shortSpan = e.target.previousElementSibling;
            const fullText = shortSpan.dataset.full;
            const shortText = shortSpan.dataset.short;

            if (shortSpan.dataset.expanded) {
                shortSpan.innerHTML = shortText;
                shortSpan.style = "max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; display:inline-block;";
                e.target.innerHTML = "[show more]";
                delete shortSpan.dataset.expanded;
            } else {
                shortSpan.innerHTML = fullText;
                shortSpan.style = "";
                e.target.innerHTML = "[show less]";
                shortSpan.dataset.expanded = "1";
            }
        }
    });

});
