// JavaScript chỉ xem trước lựa chọn và giá; PHP xác nhận giá, ưu đãi và tương thích.
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("builder-form");
    if (!form) return;
    const selects = [...form.querySelectorAll(".part-select")];
    const ownedChecks = [...form.querySelectorAll(".owned-check")];
    const money = new Intl.NumberFormat("vi-VN");
    const catalogs = new Map(selects.map((select) => [select, [...select.options]]));
    const normalizeSearch = (value) => value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").replace(/Đ/g, "D").toLocaleLowerCase("vi-VN").trim();
    const labelFor = (select) => select.closest(".component-card").querySelector(".component-label").textContent.trim();
    const owns = (select) => document.getElementById(select.id.replace("part-", "owned-"))?.checked;

    function updatePartPreview(select) {
        const card = select.closest(".component-card");
        const metadata = select.selectedOptions[0]?.dataset || {};
        const chosen = !!select.value;
        const setText = (field, value) => card.querySelector(`[data-preview-${field}]`).textContent = value;
        setText("state", !chosen ? "Chưa chọn" : owns(select) ? "Đã có" : metadata.priceKnown === "0" || metadata.availability === "contact" ? "Đã chọn · Liên hệ" : "Đã chọn · cần mua");
        card.querySelector("[data-picker-label]").textContent = chosen ? "Thay linh kiện" : "Chọn linh kiện";
        setText("name", chosen ? metadata.name : "Chọn một mã trong danh mục " + labelFor(select));
        setText("specs", chosen ? metadata.specs : "Có thể bỏ trống nhóm này. Thông số sẽ hiện sau khi chọn linh kiện.");
        setText("price", chosen ? metadata.formattedPrice : "Chưa có lựa chọn");
        setText("price-kind", chosen ? metadata.priceKind : "");
        setText("availability", chosen ? metadata.availabilityLabel + " · không phải tồn kho thực tế" : "");
        const photo = card.querySelector(".part-preview-art");
        const hasPhoto = chosen && !!metadata.image;
        let image = photo.querySelector("[data-preview-image]");
        if (hasPhoto) {
            if (!image) {
                image = document.createElement("img");
                image.dataset.previewImage = "";
                image.width = 110;
                image.height = 100;
                image.decoding = "async";
                photo.prepend(image);
            }
            image.alt = metadata.imageAlt;
            image.src = metadata.image;
        } else {
            image?.remove();
        }
        photo.hidden = !hasPhoto;
        photo.closest(".part-preview").classList.toggle("part-preview-text-only", !hasPhoto);
        setText("caption", hasPhoto ? metadata.imageCaption : "");
        const detail = card.querySelector("[data-preview-detail]");
        detail.hidden = !chosen;
        if (chosen) detail.href = metadata.detailUrl;
        else detail.removeAttribute("href");
    }

    function filterOptions(select) {
        const card = select.closest(".component-card");
        const query = normalizeSearch(card.querySelector("[data-part-search]").value);
        const selectedValue = select.value;
        const all = catalogs.get(select);
        const matches = all.filter((option) => option.value && (!query || normalizeSearch(option.dataset.search || option.textContent).includes(query)));
        // Giữ mã đang chọn ngay cả khi không khớp tìm; tìm kiếm không thay đổi cấu hình.
        const visible = all.filter((option) => !option.value || matches.includes(option) || option.value === selectedValue);
        select.replaceChildren(...visible);
        select.value = selectedValue;
        const retained = selectedValue && !matches.some((option) => option.value === selectedValue);
        card.querySelector("[data-search-result]").textContent = `${matches.length} / ${all.length - 1} mã phù hợp.${retained ? " Vẫn giữ mã đang chọn." : ""}`;
    }

    function previewSelection() {
        const chosen = selects.filter((select) => select.value);
        const knownPrice = (select) => select.selectedOptions[0].dataset.priceKnown === "0" ? 0 : Number(select.selectedOptions[0].dataset.price || 0);
        const total = chosen.reduce((sum, select) => sum + (owns(select) ? 0 : knownPrice(select)), 0);
        const configurationTotal = chosen.reduce((sum, select) => sum + knownPrice(select), 0);
        const unpricedPurchases = chosen.filter((select) => select.selectedOptions[0].dataset.priceKnown === "0" && !owns(select));
        const unpricedSelection = chosen.some((select) => select.selectedOptions[0].dataset.priceKnown === "0");
        document.getElementById("preview-total").textContent = money.format(total) + " ₫";
        document.getElementById("price-note").textContent = unpricedPurchases.length
            ? "Chưa đủ giá phần cần mua — tạm tính mã đã có giá, cần liên hệ; chưa xác nhận ưu đãi"
            : "Xem trước phần cần mua, chưa gửi và chưa xác nhận ưu đãi";
        const purchaseNote = document.getElementById("unpriced-purchase-note");
        purchaseNote.hidden = !unpricedPurchases.length;
        purchaseNote.textContent = "Chưa có giá phần cần mua: " + unpricedPurchases.map(labelFor).join(", ") + ".";
        document.getElementById("configuration-value").textContent = "Giá trị tham chiếu toàn bộ lựa chọn: " + money.format(configurationTotal) + " ₫ · " + chosen.filter(owns).length + " nhóm đã có · chưa gửi";
        document.getElementById("unpriced-selection-note").hidden = !unpricedSelection;
        document.querySelectorAll(".deal-note").forEach((note) => note.hidden = true);
        document.getElementById("selection-count").textContent = chosen.length;
        document.getElementById("selection-progress").style.width = (chosen.length / selects.length * 100) + "%";
        document.querySelector('[role="progressbar"]').setAttribute("aria-valuenow", chosen.length);
        document.getElementById("pending-note").hidden = false;
        document.getElementById("report")?.classList.add("is-stale");
        document.getElementById("assistant")?.classList.add("is-stale");
        document.querySelector(".builder-checked-results")?.classList.add("is-stale");
        document.querySelectorAll("[data-checked-stale-notice]").forEach((notice) => notice.hidden = false);
        // Không phân tích nhu cầu trên báo cáo cũ sau khi đổi linh kiện.
        const assistantFields = document.querySelector("[data-assistant-fields]");
        if (assistantFields) assistantFields.disabled = true;
        document.querySelectorAll("[data-requires-current], [data-recommendation-current]").forEach((button) => button.disabled = true);
        selects.forEach(updatePartPreview);
        document.querySelectorAll(".rig-node").forEach((node) => {
            const type = node.dataset.rigType;
            const selected = !!document.getElementById("part-" + type)?.value;
            node.classList.toggle("chosen", selected);
            const isOwned = selected && document.getElementById("owned-" + type)?.checked;
            node.classList.toggle("owned", isOwned);
            const label = node.querySelector("span").textContent.trim();
            node.setAttribute("aria-label", `Đến nhóm ${label} — ${!selected ? "Chưa chọn" : isOwned ? "Đã có" : "Đã chọn"}`);
        });
    }
    selects.forEach((select) => {
        const card = select.closest(".component-card");
        card.querySelector(".part-search").hidden = false;
        if (select.value) card.querySelector(".part-picker").open = false;
        card.querySelector("[data-part-search]").addEventListener("input", () => filterOptions(select));
        card.querySelector("[data-search-clear]").addEventListener("click", () => {
            const input = card.querySelector("[data-part-search]");
            input.value = "";
            filterOptions(select);
            input.focus();
        });
        select.addEventListener("change", () => {
            filterOptions(select);
            previewSelection();
        });
    });
    ownedChecks.forEach((input) => input.addEventListener("change", previewSelection));
    document.querySelectorAll(".rig-node").forEach((node) => node.addEventListener("click", () => {
        const select = document.getElementById("part-" + node.dataset.rigType);
        select.closest(".component-card").querySelector(".part-picker").open = true;
        select.focus({preventScroll:true});
        select.scrollIntoView({behavior:window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth", block:"center"});
    }));
    document.getElementById("demo-button")?.addEventListener("click", () => {
        selects.forEach((select) => {
            select.closest(".component-card").querySelector("[data-part-search]").value = "";
            filterOptions(select);
            select.value = catalogs.get(select).find((option) => option.dataset.reference === "1")?.value || "";
        });
        ownedChecks.forEach((input) => input.checked = false);
        previewSelection();
        form.requestSubmit();
    });
    // Mở/đóng chỉ thay cách đọc báo cáo, tuyệt đối không gửi hoặc thay lựa chọn.
    const ruleDisclosures = [...document.querySelectorAll("#report details[data-rule-group]")];
    const expandRules = document.querySelector("[data-expand-rules]");
    if (expandRules && ruleDisclosures.length) {
        expandRules.hidden = false;
        const expandLabel = expandRules.textContent;
        const updateExpandLabel = () => {
            expandRules.textContent = ruleDisclosures.every((group) => group.open)
                ? "Thu gọn phần WARNING / PASS" : expandLabel;
        };
        ruleDisclosures.forEach((group) => group.addEventListener("toggle", updateExpandLabel));
        expandRules.addEventListener("click", () => {
            const open = !ruleDisclosures.every((group) => group.open);
            ruleDisclosures.forEach((group) => group.open = open);
            updateExpandLabel();
        });
    }
    // Nhu cầu đang gõ khác nhu cầu đã gửi; báo cáo kỹ thuật vẫn giữ nguyên.
    const requirementsForm = document.querySelector("[data-requirements-form]");
    if (requirementsForm) {
        const inputs = [...requirementsForm.querySelectorAll("[data-needs-input]")];
        const initialValues = inputs.map((input) => input.value);
        const updateRequirementsNotice = () => {
            const pending = requirementsForm.dataset.pending === "1"
                || inputs.some((input, index) => input.value !== initialValues[index]);
            document.querySelector("[data-requirements-pending-note]").hidden = !pending;
            document.querySelector("[data-requirements-analysis]").classList.toggle("requirements-draft", pending);
        };
        inputs.forEach((input) => {
            input.addEventListener("input", updateRequirementsNotice);
            input.addEventListener("change", updateRequirementsNotice);
        });
        updateRequirementsNotice();
    }
    // Bảng cao hơn vùng nhìn phải cuộn cùng trang để nút lưu/giỏ không bị ghim ngoài màn hình.
    const summary = document.querySelector(".summary-column");
    if (summary) {
        const fitSummary = () => {
            const top = Number.parseFloat(getComputedStyle(summary).top) || 0;
            summary.classList.toggle("summary-fits", window.innerWidth >= 992 && summary.offsetHeight <= window.innerHeight - top - 16);
        };
        new ResizeObserver(fitSummary).observe(summary);
        window.addEventListener("resize", fitSummary);
        fitSummary();
    }
    if (form.dataset.pending === "1") previewSelection();
});
