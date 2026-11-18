<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>


<div class="card shadow-sm rounded-4" style="max-width: 400px; margin: auto;">
  <div class="card-body">
    <form id="supplierForm" action="submit_supplier.php" method="POST">
      <!-- Header -->
      <!-- Header -->
      <h6 class="fw-bold mb-1">Select Supplier</h6>
      <!-- Supplier Dropdown -->
      <div class="mb-2">
        <select id="supplierSelect" name="supplier_id" class="form-select" required>
          <option selected disabled value="">Select Supplier</option>
        </select>
      </div>
      <small id="supplierDescription" class="text-muted d-block mb-1"></small>
      <small id="supplierContact" class="text-muted d-block mb-1"></small>
      <small id="supplierTerms" class="text-muted d-block mb-3"></small>

      <!-- Add Item Link -->
      <a href="#" id="addItemBtn" class="d-block mb-2 btn btn-primary rounded-pill" style="width: 100px;">Add Item</a>

      <!-- Item Selection Dropdown Area -->
      <div id="itemSelectArea" class="mb-3 d-none">
        <select id="availableItemSelect" class="form-select mb-2"></select>
        <div class="d-flex gap-2">
          <button type="button" id="confirmAddBtn" class="btn btn-sm btn-outline-success">Add to List</button>
          <button type="button" id="cancelAddBtn" class="btn btn-sm btn-outline-secondary">Cancel</button>
        </div>
      </div>

      <!-- Selected Item List -->
      <ul id="selectedItems" class="list-group list-group-flush"></ul>

      <div class="mt-3 text-end fw-bold">
        Total: ₱<span id="totalAmount">0.00</span>
      </div>
      <!-- Approve Button -->
      <div class="text-center mt-4">
        <button type="submit" class="btn btn-outline-primary rounded-pill w-100">Approve</button>
      </div>
    </form>
  </div>
</div>
<script>
  let availableItems = []; // All items from the selected supplier
  let selectedItems = []; // IDs of selected items
  let supplierData = [];

  $(document).ready(function() {
    // ✅ Define the function INSIDE $(document).ready
    function rebuildItemDropdown() {
      const select = $('#availableItemSelect').empty();
      const filtered = availableItems.filter(item => !selectedItems.includes(item.item_id));

      if (filtered.length === 0) {
        $('#itemSelectArea').addClass('d-none');
        return;
      }

      filtered.forEach(item => {
        const label = `${item.item_name} - ₱${item.unit_price} (${item.description})`;
        select.append(`<option value="${item.item_id}">${label}</option>`);
      });

      $('#itemSelectArea').removeClass('d-none'); // Always show when items are available
    }



    // Load suppliers into dropdown
    $.getJSON('data/get_suppliers.php', function(data) {
      supplierData = data;

      data.forEach(supplier => {
        $('#supplierSelect').append(
          `<option value="${supplier.supplier_id}">${supplier.supplier_name}</option>`
        );
      });
    });

    $('#supplierSelect').change(function() {
      const supplierId = $(this).val();
      const supplier = supplierData.find(s => s.supplier_id == supplierId);

      // Update supplier info display
      $('#supplierDescription').text('CONTACT PERSON: ' + (supplier.contact_person || 'No description'));
      $('#supplierContact').text('CONTACT NUMBER: ' + (supplier.contact_number || 'N/A'));
      $('#supplierTerms').text('TERMS OF PAYMENT: ' + (supplier.payment_terms || 'N/A'));

      // Reset items
      selectedItems = [];
      availableItems = [];
      $('#selectedItems').empty();
      $('#itemSelectArea').addClass('d-none');

      // Fetch items for that supplier
      $.getJSON('data/get_items.php?supplier_id=' + supplierId, function(data) {
        availableItems = data;
      });
    });

    $('#addItemBtn').click(function(e) {
      e.preventDefault();

      if (availableItems.length === 0 || selectedItems.length >= 4) return;

      rebuildItemDropdown(); // 💡 Always refresh the dropdown before showing
      $('#itemSelectArea').removeClass('d-none');
    });


    $('#confirmAddBtn').click(function() {
      const selectedId = $('#availableItemSelect').val();
      const selectedItem = availableItems.find(item => item.item_id == selectedId);

      if (!selectedItem || selectedItems.includes(selectedItem.item_id)) return;

      selectedItems.push(selectedItem.item_id);

      // $('#selectedItems').append(`
      //   <li class="list-group-item d-flex justify-content-between align-items-start flex-column px-2" data-id="${selectedItem.item_id}">
      //     <input type="hidden" name="items[]" value="${selectedItem.item_id}">
      //     <div class="w-100 d-flex justify-content-between align-items-center">
      //       <div>
      //         <strong>${selectedItem.item_name}</strong>
      //         <br><small class="text-muted">₱${selectedItem.unit_price} - ${selectedItem.description}</small>
      //       </div>
      //       <button type="button" class="btn btn-sm btn-outline-danger btn-remove mt-1">🗑️</button>
      //     </div>
      //   </li>
      // `);
      $('#selectedItems').append(`
        <li class="list-group-item px-2" data-id="${selectedItem.item_id}" data-price="${selectedItem.unit_price}" data-qty="1">
          <input type="hidden" name="items[]" value="${selectedItem.item_id}">
          <div class="d-flex justify-content-between align-items-start flex-column">
            <div class="d-flex justify-content-between w-100 align-items-center">
              <div>
                <strong>${selectedItem.item_name}</strong><br>
                <small class="text-muted">${selectedItem.description}</small><br>
                <small class="text-muted">Unit Price: ₱${parseFloat(selectedItem.unit_price).toFixed(2)}</small>
              </div>
              <button type="button" class="btn btn-sm btn-outline-danger btn-remove ms-2">🗑️</button>
            </div>
            <div class="mt-2 d-flex align-items-center gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary btn-minus">−</button>
              <span class="item-qty">1</span>
              <button type="button" class="btn btn-sm btn-outline-primary btn-add">+</button>
              <span class="ms-auto">Subtotal: ₱<span class="item-subtotal">${parseFloat(selectedItem.unit_price).toFixed(2)}</span></span>
            </div>
          </div>
        </li>
      `);

      updateTotalAmount(); // update grand total



      $('#itemSelectArea').addClass('d-none');

      // ✅ Refresh the dropdown to exclude the one just added
      rebuildItemDropdown();
    });


    $('#cancelAddBtn').click(function() {
      $('#itemSelectArea').addClass('d-none');
    });

    $(document).on('click', '.btn-remove', function() {
      const listItem = $(this).closest('li');
      const itemId = listItem.data('id');

      // Remove from selectedItems
      selectedItems = selectedItems.filter(id => id != itemId);
      listItem.remove();

      rebuildItemDropdown();

      // Automatically show dropdown if we can still add items
      if (selectedItems.length < 4 && availableItems.length > selectedItems.length) {
        $('#itemSelectArea').removeClass('d-none');
      }
    });


    // Make the list sortable
    $('#selectedItems').sortable({
      update: function() {
        selectedItems = $('#selectedItems li').map(function() {
          return $(this).data('id');
        }).get();
      }
    });


    // Add quantity
    $(document).on('click', '.btn-add', function() {
      const listItem = $(this).closest('li');
      let qty = parseInt(listItem.attr('data-qty'));
      const price = parseFloat(listItem.attr('data-price'));

      qty++;
      listItem.attr('data-qty', qty);
      listItem.find('.item-qty').text(qty);
      listItem.find('.item-subtotal').text((qty * price).toFixed(2));

      updateTotalAmount();
    });

    // Subtract quantity
    $(document).on('click', '.btn-minus', function() {
      const listItem = $(this).closest('li');
      let qty = parseInt(listItem.attr('data-qty'));
      const price = parseFloat(listItem.attr('data-price'));

      if (qty > 1) {
        qty--;
        listItem.attr('data-qty', qty);
        listItem.find('.item-qty').text(qty);
        listItem.find('.item-subtotal').text((qty * price).toFixed(2));
        updateTotalAmount();
      }
    });

    function updateTotalAmount() {
      let total = 0;
      $('#selectedItems li').each(function() {
        const price = parseFloat($(this).attr('data-price'));
        const qty = parseInt($(this).attr('data-qty'));
        total += price * qty;
      });

      $('#totalAmount').text(total.toFixed(2));
    }

    $(document).on('click', '.btn-remove', function() {
      const listItem = $(this).closest('li');
      const itemId = listItem.data('id');

      selectedItems = selectedItems.filter(id => id != itemId);
      listItem.remove();

      rebuildItemDropdown();
      updateTotalAmount(); // ✅ recalculate
    });


  });
</script>