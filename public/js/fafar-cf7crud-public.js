window.addEventListener("DOMContentLoaded", () => {
  fafarCf7CrudSetButtonListener();

  // Set listener to stock input when file is selected
  fafarCf7CrudSetStockFileInputListener();

  /*
   * Change all CF7 text fields with 'far-crud-time-field' to
   * time fields
   */
  fafarChangeTextToTimeInputType();

  /*
   * Change all CF7 text fields with 'far-crud-datetime-field' to
   * datetime fields
   */
  fafarChangeTextToDatetimeInputType();

  /*
   * Change all CF7 text fields with 'far-crud-datetime-local-field' to
   * datetime-local fields
   */
  fafarChangeTextToDatetimeLocalInputType();

  /*
   * Uppercase value on input's that has 'far-crud-transform-uppercase'
   */
  fafarToUpperCase();

  /*
   * Lowercase value on input's that has 'far-crud-transform-lowercase'
   */
  fafarToLowerCase();

  /*
   * Capitalize value on input's that has 'far-crud-transform-capitalize'
   */
  fafarStrCapitalize();

  /*
   * Stripes every char on a value that is not a number
   * inputed on a field with 'far-crud-just-number' class
   */
  fafarJustNumber();
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

function fafarChangeTextToTimeInputType() {
  document.querySelectorAll("input.far-crud-time-field").forEach((el) => {
    el.setAttribute("type", "time");
  });
}

function fafarChangeTextToDatetimeInputType() {
  document.querySelectorAll("input.far-crud-datetime-field").forEach((el) => {
    el.setAttribute("type", "datetime");
  });
}

function fafarChangeTextToDatetimeLocalInputType() {
  document
    .querySelectorAll("input.far-crud-datetime-local-field")
    .forEach((el) => {
      el.setAttribute("type", "datetime-local");
    });
}

function fafarToUpperCase() {
  document.querySelectorAll(".far-crud-transform-uppercase").forEach((el) =>
    el.addEventListener("input", function () {
      this.value = this.value.toUpperCase();
    })
  );
}

function fafarToLowerCase() {
  document.querySelectorAll(".far-crud-transform-lowercase").forEach((el) =>
    el.addEventListener("input", function () {
      this.value = this.value.toUpperCase();
    })
  );
}

function fafarStrCapitalize() {
  document.querySelectorAll(".far-crud-transform-capitalize").forEach((el) =>
    el.addEventListener("input", function () {
      this.value = fafarCapitalizeFirstLetter(this.value.toUpperCase());
    })
  );
}

function fafarCapitalizeFirstLetter(val) {
  return String(val).charAt(0).toUpperCase() + String(val).slice(1);
}

function fafarJustNumber() {
  document.querySelectorAll(".far-crud-just-number").forEach((el) =>
    el.addEventListener("input", function (e) {
      e.target.value = e.target.value
        .split()
        .map((c) => c.replace(/[^0-9.,]/g, ""))
        .join();
    })
  );
}
