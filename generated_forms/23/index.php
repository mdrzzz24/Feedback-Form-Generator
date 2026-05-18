<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Feedback | dddd</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css' rel='stylesheet'><style>body{background:#f0f2f5;padding:40px 0}.card{border:0;border-radius:16px}.form-label{font-weight:600;color:#444}.form-control{border-radius:10px;padding:12px;border:1px solid #ddd}.form-control:focus{border-color:#4a6fa5;box-shadow:0 0 0 3px rgba(74,111,165,.15)}.form-check-input:checked{background-color:#4a6fa5;border-color:#4a6fa5}.btn-submit{background:#4a6fa5;color:#fff;border:none;border-radius:12px;padding:16px;font-weight:600;width:100%;font-size:1.1rem}.btn-submit:hover{background:#3d5d8a}</style><script>document.addEventListener('DOMContentLoaded',function(){
  var all = document.querySelectorAll('[data-parent-q]');
  all.forEach(function(el){
    var parentQ = el.getAttribute('data-parent-q');
    var parentOpt = el.getAttribute('data-parent-opt');
    var parentInputs = document.getElementsByName(parentQ + '[]');
    if(parentInputs.length === 0) parentInputs = document.getElementsByName(parentQ);
    var showIf = function(){
      var show = false;
      parentInputs.forEach(function(inp){
        if(inp.type === 'radio' || inp.type === 'checkbox') {
          if(inp.checked && inp.value==parentOpt) show=true;
        } else {
          if(inp.value == parentOpt) show=true;
        }
      });
      el.style.display = show ? '' : 'none';
    };
    parentInputs.forEach(function(inp){
      inp.addEventListener('change', showIf);
      inp.addEventListener('input', showIf);
    });
    showIf();
  });
});</script></head><body><div class='container'><div class='row justify-content-center'><div class='col-lg-8'><form action='submit.php' method='POST'><input type='hidden' name='id_event' value='23'><div class='card mb-4 shadow-sm'><div class='card-body'><h4 class='mb-2'>dddd</h4></div></div><div class='card mb-4 shadow-sm'><div class='card-body'><h5 class='border-bottom pb-2 mb-3'>Informasi Peserta</h5><div class='mb-4' id='wrap_name'><label class='form-label'>Nama Lengkap <span class="text-danger">*</span></label><input type='text' class='form-control' name='name' required></div><div class='mb-4' id='wrap_email'><label class='form-label'>Email <span class="text-danger">*</span></label><input type='email' class='form-control' name='email' required></div><div class='mb-4' id='wrap_companyName'><label class='form-label'>Company </label><input type='text' class='form-control' name='companyName' ></div><div class='mb-4' id='wrap_mobileNumber'><label class='form-label'>Phone </label><input type='tel' class='form-control' name='mobileNumber' ></div></div></div><button type='submit' class='btn btn-submit w-100'>Submit Feedback</button></form></div></div></div></body></html>