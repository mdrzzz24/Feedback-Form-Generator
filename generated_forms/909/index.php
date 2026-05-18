<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1'><title>Feedback | Anaplan</title><link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css' rel='stylesheet'><style>body{background:#f5f5dc;color:#5d4037;padding:40px 0}.card{background:#fffaf0;border:0;border-radius:16px;color:#5d4037;box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1)}.form-label{font-weight:600;color:#795548}.form-control{background:#fffaf0;color:#5d4037;border-radius:10px;padding:12px;border:1px solid rgba(0,0,0,0.1)}.form-control:focus{background:#fffaf0;color:#5d4037;border-color:#8d6e63;box-shadow:0 0 0 3px rgba(0,0,0,0.05)}.form-check-input:checked{background-color:#8d6e63;border-color:#8d6e63}.btn-submit{background:#8d6e63;color:#fff;border:none;border-radius:12px;padding:16px;font-weight:600;width:100%;font-size:1.1rem}.btn-submit:hover{background:#5d4037;color:#fff}.btn-nav{background:rgba(0,0,0,0.05);color:#5d4037;border:none;border-radius:10px;padding:10px 20px;font-weight:600}.grid-layout{display:grid;grid-template-columns:1fr 1fr;gap:20px} .grid-layout > .section-title-main {grid-column: span 2}.form-step{display:none} .form-step.active{display:block;animation:fadeIn 0.4s}@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}.text-muted{color:rgba(0,0,0,0.5)!important}</style><script>document.addEventListener('DOMContentLoaded',function(){
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
  var steps = document.querySelectorAll('.form-step');
  var currentStep = 0;
  window.moveStep = function(dir){
    steps[currentStep].classList.remove('active');
    currentStep += dir;
    steps[currentStep].classList.add('active');
    window.scrollTo(0,0);
  };
});</script></head><body><div class='container'><div class='row justify-content-center'><div class='col-lg-8'><form action='submit.php' method='POST'><input type='hidden' name='id_event' value='909'><div><div class='card mb-4 shadow-sm'><div class='card-body'><img src='../form-generator/uploads/header_1779078434_135.jpeg' class='rounded-3 mb-4 w-100' style='max-height:200px;object-fit:cover;'><h4 class='mb-2'>Anaplan</h4><p class='text-muted mb-4'>EL King Jian dot Kom</p></div></div></div><div><div class='card mb-4 shadow-sm'><div class='card-body'><h5 class='border-bottom pb-2 mb-3 section-title-main'>General Information</h5><div class='mb-4' id='wrap_name'><label class='form-label'>Nama Lengkap <span class="text-danger">*</span></label><input type='text' class='form-control' name='name' required></div><div class='mb-4' id='wrap_email'><label class='form-label'>Email <span class="text-danger">*</span></label><input type='email' class='form-control' name='email' required></div><div class='mb-4' id='wrap_companyName'><label class='form-label'>Company <span class="text-danger">*</span></label><input type='text' class='form-control' name='companyName' required></div><div class='mb-4' id='wrap_mobileNumber'><label class='form-label'>Phone <span class="text-danger">*</span></label><input type='tel' class='form-control' name='mobileNumber' required></div></div></div></div><div><div class='card mb-4 shadow-sm'><div class='card-body'><h5 class='border-bottom pb-2 mb-3 section-title-main'>Indonesian Context</h5><div class='mb-4' id='wrap_q_30'><p class='mb-3 fw-semibold'>Apakah Indonesia sudah merdeka ? <span class=\"text-danger\">*</span></p><div class='row g-2'><div class='col-md-6'><div class='form-check'><input type='radio' class='form-check-input' name='q_30[]' id='328ba1acb2364cca3d6bea25af28c2c6' value='Udah' required><label class='form-check-label' for='328ba1acb2364cca3d6bea25af28c2c6'>Udah</label></div></div><div class='col-md-6'><div class='form-check'><input type='radio' class='form-check-input' name='q_30[]' id='a6ef38910e725c395b93fdfbe25980af' value='Belum' required><label class='form-check-label' for='a6ef38910e725c395b93fdfbe25980af'>Belum</label></div></div></div></div><div class='mb-4' id='wrap_q_31' data-parent-q='q_30' data-parent-opt='Udah' style='display:none;'><p class='mb-3 fw-semibold'>Kalau sudah merdeka, Kapan merdekanya? </p><div class='row g-2'><div class='col-md-6'><div class='form-check'><input type='radio' class='form-check-input' name='q_31[]' id='fa0964b155b80efc1121bb9427a76938' value='17 Agustus' ><label class='form-check-label' for='fa0964b155b80efc1121bb9427a76938'>17 Agustus</label></div></div><div class='col-md-6'><div class='form-check'><input type='radio' class='form-check-input' name='q_31[]' id='550ebd09e7b30e51f76eff293f88dd5e' value='21 Desember' ><label class='form-check-label' for='550ebd09e7b30e51f76eff293f88dd5e'>21 Desember</label></div></div><div class='col-md-6'><div class='form-check'><input type='radio' class='form-check-input' name='q_31[]' id='d56f3c13ef5723919df8d6b3df887883' value='3 Januari' ><label class='form-check-label' for='d56f3c13ef5723919df8d6b3df887883'>3 Januari</label></div></div></div></div></div></div></div><button type='submit' class='btn btn-submit w-100'>Submit Feedback</button></form></div></div></div></body></html>