window.addEventListener("DOMContentLoaded", () => {
  fafarCf7CrudSetButtonListener();

  // Set listener to stock input when file is selected
  fafarCf7CrudSetStockFileInputListener();
});

function fafarCf7CrudSetButtonListener() {
  const buttons = document.querySelectorAll(
    "button.fafar-cf7crud-input-document-button"
  );

  buttons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const attr_name = btn.getAttribute("data-file-input-button");

      const input = document.querySelector('input[name="' + attr_name + '"]');
      if (input) input.click();
    });
  });
}

function fafarCf7CrudSetStockFileInputListener() {
  const fafar_cf7crud_stock_file_input = document.querySelectorAll(
    "input.fafar-cf7crud-stock-file-input"
  );

  fafar_cf7crud_stock_file_input.forEach((input) => {
    input.addEventListener("change", fafarCf7OnChangeCrudInputHandler);
  });
}

function fafarCf7OnChangeCrudInputHandler(event) {
  const attr_name = event.target.getAttribute("name");

  const fileName = this.files[0] ? this.files[0].name : "";
  this.setAttribute("value", fileName);
  console.log(attr_name);
  console.log(fileName);

  document
    .querySelector(
      'input[name="fafar_cf7crud_input_file_hidden_' + attr_name + '"]'
    )
    .setAttribute("value", fileName);

  const span = document.querySelector(
    'span[data-file-input-label="' + attr_name + '"]'
  );
  if (span) span.textContent = fileName ? fileName : "Selecione um arquivo";
}
