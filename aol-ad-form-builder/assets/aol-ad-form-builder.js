(function ($) {
  function parseSchema($wrap) {
    const $input = $wrap.find(".aol-ad-fb-v2-schema");
    try {
      const val = $input.val();
      const parsed = val ? JSON.parse(val) : [];
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function writeSchema($wrap, schema) {
    $wrap.find(".aol-ad-fb-v2-schema").val(JSON.stringify(schema || []));
  }

  function fieldTypes() {
    return (window.aolAdFbV2 && window.aolAdFbV2.fieldTypes) || {};
  }

  function fieldTypeIcons() {
    return (window.aolAdFbV2 && window.aolAdFbV2.fieldTypeIcons) || {};
  }

  function typeLabel(type) {
    const t = fieldTypes();
    return t[type] || type || "";
  }

  function normalizeId(id) {
    return String(id || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9_]/g, "_")
      .replace(/_{2,}/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function showModal($wrap) {
    $wrap.find(".aol-ad-fb-v2-modal").show();
  }
  function hideModal($wrap) {
    $wrap.find(".aol-ad-fb-v2-modal").hide();
  }

  function syncTypeVisibility($wrap, type) {
    const show = (sel, ok) => $wrap.find(sel).toggle(!!ok);
    show(".aol-ad-fb-v2-only--options", ["checkbox", "radio", "dropdown"].includes(type));
    show(".aol-ad-fb-v2-only--placeholder", ["text", "text_area", "number", "email", "date"].includes(type));
    show(".aol-ad-fb-v2-only--file", type === "file");
    show(".aol-ad-fb-v2-only--paragraph", type === "paragraph");
  }

  function setActiveType($wrap, type) {
    const types = fieldTypes();
    const t = types[type] ? type : "text";
    $wrap.find(".aol-ad-fb-v2-type").val(t);
    $wrap.find(".aol-ad-fb-v2-type-tab").removeClass("is-active").attr("aria-selected", "false");
    $wrap.find('.aol-ad-fb-v2-type-tab[data-type="' + t + '"]').addClass("is-active").attr("aria-selected", "true");
    syncTypeVisibility($wrap, t);
  }

  function ensureTypeTabs($wrap) {
    const $tabs = $wrap.find(".aol-ad-fb-v2-type-tabs");
    if ($tabs.children().length) return;

    const types = fieldTypes();
    const icons = fieldTypeIcons();
    Object.keys(types).forEach((k) => {
      const icon = icons[k] || "dashicons-admin-generic";
      const $btn = $('<button type="button" class="aol-ad-fb-v2-type-tab" role="tab"></button>');
      $btn.attr("data-type", k);
      $btn.attr("aria-selected", "false");
      $btn.append('<span class="dashicons ' + icon + '"></span>');
      $btn.append('<span class="aol-ad-fb-v2-type-tab__label"></span>');
      $btn.find(".aol-ad-fb-v2-type-tab__label").text(types[k]);
      $tabs.append($btn);
    });
  }

  function renderRows($wrap) {
    const schema = parseSchema($wrap);
    const $tbody = $wrap.find(".aol-ad-fb-v2-rows");
    $tbody.empty();

    const tpl = $wrap.find(".aol-ad-fb-v2-row-template")[0];
    if (!tpl) return;

    schema.forEach((f, idx) => {
      const $row = $($(tpl.content).children()[0].cloneNode(true));
      $row.attr("data-idx", idx);
      $row.find(".col-required").text(f.required ? "Yes" : "No");
      $row.find(".aol-ad-fb-v2-add--inline").attr("data-insert-at", String(idx + 1));
      renderFieldPreview($row, f);
      $tbody.append($row);
    });
  }

  function escHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function splitOptions(f) {
    const raw = String((f && f.options) || "");
    return raw
      .split(",")
      .map((x) => x.trim())
      .filter((x) => x);
  }

  function renderFieldPreview($row, f) {
    const label = f.label || f.id || "";
    const type = f.type || "text";
    const placeholder = f.placeholder || "";
    const help = f.description || "";

    const $wrap = $row.find(".aol-ad-fb-v2-preview");
    $wrap.find(".aol-ad-fb-v2-preview__label").text(label);
    $wrap.find(".aol-ad-fb-v2-preview__type").text(typeLabel(type));
    $wrap.find(".aol-ad-fb-v2-preview__help").text(help);

    const $control = $wrap.find(".aol-ad-fb-v2-preview__control");
    $control.empty();

    if (type === "separator") {
      $control.append('<hr class="aol-ad-fb-v2-separator" />');
      return;
    }

    if (type === "paragraph") {
      const text = (f.text || help || "").trim();
      $control.append('<div class="aol-ad-fb-v2-paragraph">' + escHtml(text || "…") + "</div>");
      return;
    }

    if (type === "dropdown") {
      const opts = splitOptions(f);
      const $sel = $('<select class="aol-ad-fb-v2-preview__input" disabled></select>');
      if (!opts.length) {
        $sel.append($("<option/>").text("Option 1"));
      } else {
        opts.slice(0, 20).forEach((o) => $sel.append($("<option/>").text(o)));
      }
      $control.append($sel);
      return;
    }

    if (type === "checkbox") {
      const opts = splitOptions(f);
      const list = opts.length ? opts.slice(0, 4) : ["Option 1", "Option 2"];
      const $box = $('<div class="aol-ad-fb-v2-preview__choices"></div>');
      list.forEach((o, i) => {
        $box.append(
          '<label class="aol-ad-fb-v2-choice"><input type="checkbox" disabled ' +
            (i === 0 ? "checked" : "") +
            " /> <span>" +
            escHtml(o) +
            "</span></label>"
        );
      });
      $control.append($box);
      return;
    }

    if (type === "radio") {
      const opts = splitOptions(f);
      const list = opts.length ? opts.slice(0, 4) : ["Option 1", "Option 2"];
      const $box = $('<div class="aol-ad-fb-v2-preview__choices"></div>');
      list.forEach((o, i) => {
        $box.append(
          '<label class="aol-ad-fb-v2-choice"><input type="radio" disabled name="aol-ad-fb-v2-radio-preview" ' +
            (i === 0 ? "checked" : "") +
            " /> <span>" +
            escHtml(o) +
            "</span></label>"
        );
      });
      $control.append($box);
      return;
    }

    if (type === "text_area") {
      const $ta = $('<textarea class="aol-ad-fb-v2-preview__input" rows="2" disabled></textarea>');
      if (placeholder) $ta.attr("placeholder", placeholder);
      $control.append($ta);
      return;
    }

    if (type === "file") {
      $control.append('<input class="aol-ad-fb-v2-preview__input" type="file" disabled />');
      return;
    }

    const map = {
      email: "email",
      number: "number",
      date: "date",
      text: "text",
    };
    const htmlType = map[type] || "text";
    const $in = $('<input class="aol-ad-fb-v2-preview__input" disabled />').attr("type", htmlType);
    if (placeholder) $in.attr("placeholder", placeholder);
    $control.append($in);
  }

  function openFieldEditor($wrap, field, idx) {
    ensureTypeTabs($wrap);
    const f = field || {};

    $wrap.data("editIdx", typeof idx === "number" ? idx : null);
    if (typeof idx === "number") {
      $wrap.data("insertAt", null);
    }

    $wrap.find(".aol-ad-fb-v2-id").val(f.id || "");
    $wrap.find(".aol-ad-fb-v2-label").val(f.label || "");
    $wrap.find(".aol-ad-fb-v2-required").prop("checked", !!f.required);

    $wrap.find(".aol-ad-fb-v2-placeholder").val(f.placeholder || "");
    $wrap.find(".aol-ad-fb-v2-description").val(f.description || "");
    $wrap.find(".aol-ad-fb-v2-options").val(f.options || "");
    $wrap.find(".aol-ad-fb-v2-text").val(f.text || "");
    $wrap.find(".aol-ad-fb-v2-height").val(f.height || 0);
    $wrap.find(".aol-ad-fb-v2-allowed-file-types").val(f.allowed_file_types || "");
    $wrap.find(".aol-ad-fb-v2-file-max-size").val(f.file_max_size || "");

    setActiveType($wrap, f.type || "text");
    showModal($wrap);
  }

  function readFieldFromEditor($wrap) {
    const i18n = (window.aolAdFbV2 && window.aolAdFbV2.i18n) || {};
    const idRaw = $wrap.find(".aol-ad-fb-v2-id").val();
    const id = normalizeId(idRaw);
    const label = String($wrap.find(".aol-ad-fb-v2-label").val() || "").trim();
    const type = $wrap.find(".aol-ad-fb-v2-type").val();

    if (!id) throw new Error(i18n.idRequired || "ID required");
    if (!label) throw new Error(i18n.labelRequired || "Label required");

    return {
      id,
      label,
      type,
      required: $wrap.find(".aol-ad-fb-v2-required").is(":checked") ? 1 : 0,
      placeholder: String($wrap.find(".aol-ad-fb-v2-placeholder").val() || "").trim(),
      description: String($wrap.find(".aol-ad-fb-v2-description").val() || "").trim(),
      options: String($wrap.find(".aol-ad-fb-v2-options").val() || "").trim(),
      text: String($wrap.find(".aol-ad-fb-v2-text").val() || "").trim(),
      height: parseInt($wrap.find(".aol-ad-fb-v2-height").val() || "0", 10) || 0,
      allowed_file_types: String($wrap.find(".aol-ad-fb-v2-allowed-file-types").val() || "").trim(),
      file_max_size: parseInt($wrap.find(".aol-ad-fb-v2-file-max-size").val() || "0", 10) || 0,
    };
  }

  function move(schema, fromIdx, toIdx) {
    if (toIdx < 0 || toIdx >= schema.length) return schema;
    const copy = schema.slice();
    const [it] = copy.splice(fromIdx, 1);
    copy.splice(toIdx, 0, it);
    return copy;
  }

  $(function () {
    $(".aol-ad-fb-v2").each(function () {
      const $wrap = $(this);
      renderRows($wrap);

      $wrap.on("click", ".aol-ad-fb-v2-add", function () {
        const raw = $(this).attr("data-insert-at");
        if (raw !== undefined && raw !== "") {
          const n = parseInt(raw, 10);
          $wrap.data("insertAt", isNaN(n) ? null : n);
        } else {
          $wrap.data("insertAt", null);
        }
        openFieldEditor($wrap, { type: "text" }, null);
      });

      $wrap.on("click", ".aol-ad-fb-v2-edit", function () {
        const idx = parseInt($(this).closest("tr").attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        openFieldEditor($wrap, schema[idx], idx);
      });

      // Row click-to-edit
      $wrap.on("click", "tr.aol-ad-fb-v2-row", function (e) {
        const $t = $(e.target);
        if ($t.closest(".aol-ad-fb-v2-row__panel").length) return;
        const idx = parseInt($(this).attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        openFieldEditor($wrap, schema[idx], idx);
      });

      // Keyboard: Enter/Space to edit
      $wrap.on("keydown", "tr.aol-ad-fb-v2-row", function (e) {
        if (e.key !== "Enter" && e.key !== " ") return;
        e.preventDefault();
        const idx = parseInt($(this).attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        openFieldEditor($wrap, schema[idx], idx);
      });

      $wrap.on("click", ".aol-ad-fb-v2-delete", function () {
        const i18n = (window.aolAdFbV2 && window.aolAdFbV2.i18n) || {};
        if (!window.confirm(i18n.confirmDel || "Delete?")) return;
        const idx = parseInt($(this).closest("tr").attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        schema.splice(idx, 1);
        writeSchema($wrap, schema);
        renderRows($wrap);
      });

      $wrap.on("click", ".aol-ad-fb-v2-up", function () {
        const idx = parseInt($(this).closest("tr").attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        writeSchema($wrap, move(schema, idx, idx - 1));
        renderRows($wrap);
      });

      $wrap.on("click", ".aol-ad-fb-v2-down", function () {
        const idx = parseInt($(this).closest("tr").attr("data-idx"), 10);
        const schema = parseSchema($wrap);
        writeSchema($wrap, move(schema, idx, idx + 1));
        renderRows($wrap);
      });

      $wrap.on("click", ".aol-ad-fb-v2-type-tab", function () {
        setActiveType($wrap, $(this).attr("data-type"));
      });

      $wrap.on("click", ".aol-ad-fb-v2-close, .aol-ad-fb-v2-cancel", function () {
        $wrap.data("insertAt", null);
        hideModal($wrap);
      });

      $wrap.on("click", ".aol-ad-fb-v2-modal", function (e) {
        if (e.target === this) {
          $wrap.data("insertAt", null);
          hideModal($wrap);
        }
      });

      $wrap.on("click", ".aol-ad-fb-v2-save", function () {
        try {
          const schema = parseSchema($wrap);
          const idx = $wrap.data("editIdx");
          const insertAt = $wrap.data("insertAt");
          const f = readFieldFromEditor($wrap);

          if (typeof idx === "number" && idx !== null) {
            schema[idx] = f;
          } else if (typeof insertAt === "number" && !isNaN(insertAt)) {
            const at = Math.max(0, Math.min(insertAt, schema.length));
            schema.splice(at, 0, f);
          } else {
            schema.push(f);
          }

          $wrap.data("insertAt", null);
          writeSchema($wrap, schema);
          hideModal($wrap);
          renderRows($wrap);
        } catch (err) {
          window.alert(err && err.message ? err.message : "Error");
        }
      });
    });
  });
})(jQuery);

