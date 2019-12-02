(function ($){
  $(document).on('keyup', 'input[id^=edit-book-id]', function () {
    // format the value for the book ID.
    var val = this.value.replace(/\D/g, '');
    var newVal = '';
    if(val.length > 4) {
       this.value = val;
    }
    if((val.length > 3) && (val.length < 9)) {
       newVal += val.substr(0, 3) + '-';
       val = val.substr(3);
    }
     newVal += val;
     this.value = newVal.substring(0, 8);
  });
  $('input[id=edit-isbn]').keyup(function (e) {
    if (/\D/g.test(this.value)) {
      // Filter non-digits from input value.
      this.value = this.value.replace(/\D/g, '');
    }
  });
})(jQuery);
