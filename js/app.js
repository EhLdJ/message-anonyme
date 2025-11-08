// Simple UI helpers used by prototype pages
document.addEventListener('click', e=>{
    if(e.target.matches('[data-copy]')){
      const target = document.querySelector(e.target.getAttribute('data-copy'));
      if(target){ navigator.clipboard.writeText(target.value || target.innerText).then(()=> {
        e.target.innerText='Copié!';
        setTimeout(()=> e.target.innerText='Copier',1200)
      }) }
    }
  });
  
  // Card preview & "generate image" simulation
  function initCardEditor(opts = {}) {
    const textEl = document.getElementById('card_text');
    const preview = document.getElementById('card_preview');
    const paletteInputs = document.querySelectorAll('.swatch');
    function render(){
      const txt = textEl ? textEl.value : opts.text || 'Ma carte';
      const sel = document.querySelector('.swatch.selected')?.dataset.color || '#5b6cff';
      preview.style.background = sel;
      preview.innerText = txt || ' ';
    }
    if(textEl) textEl.addEventListener('input', render);
    paletteInputs.forEach(s=>{
      s.addEventListener('click', (ev)=>{
        paletteInputs.forEach(p=>p.classList.remove('selected'));
        s.classList.add('selected');
        render();
      })
    })
    render();
  
    // generate "image" -> dataURL
    const genBtn = document.getElementById('generate_image');
    if(genBtn){
      genBtn.addEventListener('click', ()=>{
        // create canvas dynamically
        const cv = document.createElement('canvas');
        cv.width = 1080; cv.height = 1080;
        const ctx = cv.getContext('2d');
        const bg = document.querySelector('.swatch.selected')?.dataset.color || '#5b6cff';
        ctx.fillStyle = bg; ctx.fillRect(0,0,cv.width, cv.height);
        ctx.fillStyle = '#fff'; ctx.font = '40px sans-serif'; 
        const text = (textEl && textEl.value) || opts.text || 'Message';
        // wrap text (simple)
        const words = text.split(' ');
        let line=''; let y=140;
        for(let n=0;n<words.length;n++){
          const test = line + words[n] + ' ';
          const metrics = ctx.measureText(test);
          if(metrics.width > 900 && line){ ctx.fillText(line,80,y); line = words[n] + ' '; y+=60; }
          else line = test;
        }
        ctx.fillText(line,80,y);
        const data = cv.toDataURL('image/png');
        const img = document.getElementById('generated_img');
        if(img){ img.src = data; img.style.display='block' }
        // show download link
        const dl = document.getElementById('download_image');
        if(dl){ dl.href = data; dl.download = 'message.png'; dl.style.display='inline-block' }
      })
    }
  }
  window.initCardEditor = initCardEditor;
  