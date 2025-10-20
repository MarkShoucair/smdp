// Copy button functionality
document.addEventListener('click', function(e){
  if (!e.target.matches('.smdp-copy-btn')) return;
  var text = e.target.getAttribute('data-text');
  navigator.clipboard.writeText(text).then(function(){
    e.target.textContent = 'Copied!';
    setTimeout(() => e.target.textContent = 'Copy', 2000);
  });
});

// Item picker and form functionality
jQuery(document).ready(function($) {
    console.log("[SMDP] Admin JS loaded");

    // Tab navigation with hash support
    function switchTab(tabId) {
        $(".nav-tab").removeClass("nav-tab-active");
        $(".smdp-help-tab").removeClass("active").hide();

        $('a[href="' + tabId + '"]').addClass("nav-tab-active");
        $(tabId).addClass("active").show();

        // Update URL hash without scrolling
        if (history.pushState) {
            history.pushState(null, null, tabId);
        } else {
            location.hash = tabId;
        }
    }

    // Handle tab clicks
    $(".nav-tab").on("click", function(e) {
        e.preventDefault();
        var tabId = $(this).attr("href");
        switchTab(tabId);
    });

    // On page load, check for hash and switch to that tab
    if (window.location.hash) {
        var hash = window.location.hash;
        if ($(hash).length) {
            switchTab(hash);
        }
    }

    // Sync Locations button
    $("#smdp-sync-locations-btn").on("click", function() {
        var $btn = $(this);
        var $status = $("#smdp-sync-locations-status");
        var originalHtml = $btn.html();

        $btn.prop("disabled", true);
        $btn.html('<span class="dashicons dashicons-update dashicons-spin" style="vertical-align:middle;"></span> Syncing...');
        $status.html('<span style="color:#666;">Fetching locations...</span>');

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "smdp_sync_locations",
                nonce: smdpAdmin.sync_locations_nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');
                    setTimeout(function() {
                        location.reload(); // Reload to show the locations
                    }, 1000);
                } else {
                    $status.html('<span style="color:#dc3232;">✗ ' + (response.data || 'Error syncing locations') + '</span>');
                    $btn.prop("disabled", false);
                    $btn.html(originalHtml);
                }
            },
            error: function() {
                $status.html('<span style="color:#dc3232;">✗ Connection error</span>');
                $btn.prop("disabled", false);
                $btn.html(originalHtml);
            }
        });
    });

    // Radio button visual feedback
    $("input[name='smdp_bill_lookup_method']").on("change", function() {
        $(".smdp-radio-option").removeClass("active");
        $(this).closest(".smdp-radio-option").addClass("active");
    });

    // Search functionality for all pickers
    $(".smdp-search-box").on("keyup", function() {
        var search = $(this).val().toLowerCase();
        var target = $(this).attr("id").replace("-search", "");
        var dropdown = $("#" + target + "-dropdown");

        console.log("[SMDP] Searching:", search, "Target:", target);

        if (search.length > 0) {
            dropdown.addClass("active");
            dropdown.find(".smdp-item-option").each(function() {
                var name = $(this).data("name").toLowerCase();
                if (name.indexOf(search) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        } else {
            dropdown.removeClass("active");
        }
    });

    // Select item for all pickers
    $(".smdp-item-option").on("click", function() {
        var id = $(this).data("id");
        var name = $(this).data("name");
        var target = $(this).data("target");

        console.log("[SMDP] Item selected - ID:", id, "Name:", name, "Target:", target);

        // Build the correct hidden field ID
        var hiddenFieldId;
        if (target === "help" || target === "bill" || target === "location") {
            hiddenFieldId = "#" + target + "-item-id";
        } else if (target === "table-item") {
            hiddenFieldId = "#table-item-id";
        } else {
            hiddenFieldId = "#" + target + "-id";
        }

        $(hiddenFieldId).val(id);
        $("#" + target + "-search").val("");
        $("#" + target + "-dropdown").removeClass("active");

        var $selected = $("#" + target + "-selected");
        $selected.addClass("active");

        // Update the selected text properly
        if ($selected.find(".selected-name").length) {
            $selected.find(".selected-name").text(name);
        } else {
            // For simple selected items without .selected-name span
            var clearLink = $selected.find(".smdp-clear-selection").prop("outerHTML") || "";
            $selected.html("<strong>Selected:</strong> " + name + " " + clearLink);
        }

        console.log("[SMDP] Hidden field " + hiddenFieldId + " value set to:", $(hiddenFieldId).val());
    });

    // Clear selection for all pickers
    $(".smdp-clear-selection").on("click", function(e) {
        e.preventDefault();
        var target = $(this).data("target");

        // Build the correct hidden field ID
        var hiddenFieldId;
        if (target === "help" || target === "bill" || target === "location") {
            hiddenFieldId = "#" + target + "-item-id";
        } else if (target === "table-item") {
            hiddenFieldId = "#table-item-id";
        } else {
            hiddenFieldId = "#" + target + "-id";
        }

        $(hiddenFieldId).val("");
        $("#" + target + "-selected").removeClass("active");
        $("#" + target + "-search").val("");
        $("#" + target + "-search").focus();
    });

    // Close dropdown when clicking outside
    $(document).on("click", function(e) {
        if (!$(e.target).closest(".smdp-item-picker").length) {
            $(".smdp-item-dropdown").removeClass("active");
        }
    });

    // AJAX form submission for adding table items
    $("input[name='add_table_item']").closest("form").on("submit", function(e) {
        e.preventDefault();

        var $form = $(this);
        var $status = $("#add-table-item-status");
        var $btn = $form.find("input[name='add_table_item']");
        var tableNum = $form.find("input[name='table_item_number']").val().trim();
        var itemId = $("#table-item-id").val();

        if (!tableNum || !itemId) {
            $status.html('<span style="color:#dc3232;">✗ Please enter a table number and select an item</span>');
            return false;
        }

        $btn.prop("disabled", true);
        $status.html('<span style="color:#666;">Adding...</span>');

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "smdp_add_table_item",
                nonce: smdpAdmin.add_table_item_nonce,
                table_number: tableNum,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');

                    // Clear form
                    $form.find("input[name='table_item_number']").val("");
                    $("#table-item-id").val("");
                    $("#table-item-selected").removeClass("active");
                    $("#table-item-search").val("");

                    // Reload page after 1 second to show new table in list
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color:#dc3232;">✗ ' + (response.data || 'Error adding table item') + '</span>');
                    $btn.prop("disabled", false);
                }
            },
            error: function() {
                $status.html('<span style="color:#dc3232;">✗ Connection error</span>');
                $btn.prop("disabled", false);
            }
        });
    });

    // AJAX form submission for adding table with customer ID
    $("input[name='add_table_button']").closest("form").on("submit", function(e) {
        e.preventDefault();

        var $form = $(this);
        var $status = $("#add-table-status");
        var $btn = $form.find("input[name='add_table_button']");
        var tableNum = $form.find("input[name='new_table']").val().trim();
        var customerId = $form.find("input[name='new_table_customer']").val().trim();

        if (!tableNum || !customerId) {
            $status.html('<span style="color:#dc3232;">✗ Please enter both table number and customer ID</span>');
            return false;
        }

        $btn.prop("disabled", true);
        $status.html('<span style="color:#666;">Adding...</span>');

        $.ajax({
            url: ajaxurl,
            method: "POST",
            data: {
                action: "smdp_add_table",
                nonce: smdpAdmin.add_table_nonce,
                table_number: tableNum,
                customer_id: customerId
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color:#46b450;">✓ ' + response.data.message + '</span>');

                    // Clear form
                    $form.find("input[name='new_table']").val("");
                    $form.find("input[name='new_table_customer']").val("");

                    // Reload page after 1 second to show new table in list
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $status.html('<span style="color:#dc3232;">✗ ' + (response.data || 'Error adding table') + '</span>');
                    $btn.prop("disabled", false);
                }
            },
            error: function() {
                $status.html('<span style="color:#dc3232;">✗ Connection error</span>');
                $btn.prop("disabled", false);
            }
        });
    });
});
