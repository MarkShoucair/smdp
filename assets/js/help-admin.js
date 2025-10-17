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
        if (target === "help" || target === "bill") {
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
        $selected.find(".selected-name").text(name);

        console.log("[SMDP] Hidden field " + hiddenFieldId + " value set to:", $(hiddenFieldId).val());
    });

    // Clear selection for all pickers
    $(".smdp-clear-selection").on("click", function(e) {
        e.preventDefault();
        var target = $(this).data("target");

        // Build the correct hidden field ID
        var hiddenFieldId;
        if (target === "help" || target === "bill") {
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

    // Debug form submission
    $("form").on("submit", function(e) {
        var formData = $(this).serializeArray();
        console.log("[SMDP] Form submitting with data:", formData);

        // Check if this is the table item form
        if ($(this).find("input[name='table_item_number']").length) {
            var tableNum = $("input[name='table_item_number']").val();
            var itemId = $("#table-item-id").val();
            console.log("[SMDP] Table Item Form - Number:", tableNum, "Item ID:", itemId);

            if (!tableNum || !itemId) {
                alert("Please enter a table number and select an item!");
                e.preventDefault();
                return false;
            }
        }
    });
});
