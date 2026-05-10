<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
session_start();
$base = __DIR__;
require_once $base . '/config/env.php';
require_once $base . '/src/supabase.php';
require_once $base . '/src/helpers.php';

$item_id = $_GET['id'] ?? '';
if (!$item_id) { header('Location: index.php'); exit; }
$item = fetch_item($item_id);
if (!$item) { header('Location: index.php'); exit; }
$sizes     = fetch_item_sizes($item_id);
$modifiers = fetch_item_modifiers($item_id);

$steps = [];
if (count($sizes) > 1) {
    $steps[] = ['key'=>'__size__','label'=>'What size would you like?','ui_type'=>'radio','min_select'=>1,'max_select'=>1,
        'options'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['label'],'price_delta'=>(float)$s['price'],'is_size'=>true],$sizes)];
}
foreach ($modifiers as $mg) {
    $steps[] = ['key'=>$mg['id'],'label'=>$mg['name'].'?','ui_type'=>$mg['ui_type'],
        'min_select'=>(int)$mg['min_select'],'max_select'=>(int)$mg['max_select'],
        'options'=>array_map(fn($o)=>['id'=>$o['id'],'name'=>$o['name'],'price_delta'=>(float)$o['price_delta'],'default_selected'=>(bool)$o['default_selected']],$mg['options'])];
}

$base_price  = count($sizes)===1?(float)$sizes[0]['price']:0;
$single_size = count($sizes)===1?$sizes[0]['label']:null;
$basket_count = array_sum(array_column($_SESSION['basket']??[],'qty'));
$host = ((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.$_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?=htmlspecialchars($item['name'])?> &mdash; Cedar Grove</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f5f5f4;color:#1a1a1a}a{text-decoration:none;color:inherit}
.hdr{background:#fff;border-bottom:1px solid #e5e5e5;padding:12px 20px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.bk{font-size:14px;color:#1D9E75;font-weight:500}.logo{display:flex;align-items:center;gap:10px}
.av{width:36px;height:36px;background:#1D9E75;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;flex-shrink:0}
.logo strong{font-size:15px}.bkt{display:flex;align-items:center;gap:6px;background:#1D9E75;color:#fff;padding:8px 14px;border-radius:20px;font-size:13px;font-weight:600;position:relative}
.bkt svg{width:16px;height:16px}.bdg{position:absolute;top:-4px;right:-4px;background:#e53e3e;color:#fff;width:18px;height:18px;border-radius:50%;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center}
.wrap{max-width:600px;margin:0 auto;padding:20px}
.cw{background:#fff;border-radius:16px;border:1px solid #e5e5e5;box-shadow:0 2px 20px rgba(0,0,0,.08);overflow:hidden}
.ch{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid #eee;background:linear-gradient(135deg,#1D9E75,#0F6E56)}
.ca{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.2);color:#fff;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:2px solid rgba(255,255,255,.4)}
.ch-info{flex:1}.ch-info strong{font-size:16px;color:#fff;display:block}.ch-info small{font-size:12px;color:rgba(255,255,255,.8)}
/* Voice indicator */
.voice-bar{display:flex;align-items:center;gap:10px;padding:10px 20px;background:#f8fffe;border-bottom:1px solid #e5e5e5}
.mic-btn{width:44px;height:44px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:20px;transition:all .2s;flex-shrink:0}
.mic-btn.idle{background:#e5e5e5;color:#666}
.mic-btn.listening{background:#e53e3e;color:#fff;animation:pulse 1s infinite}
.mic-btn.speaking{background:#1D9E75;color:#fff}
@keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(229,62,62,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 8px rgba(229,62,62,0)}}
.voice-status{font-size:13px;color:#666;flex:1}
.voice-status strong{color:#1D9E75}
.voice-waves{display:flex;align-items:center;gap:2px;height:20px}
.voice-waves span{width:3px;background:#1D9E75;border-radius:3px;animation:wave 1s ease-in-out infinite}
.voice-waves span:nth-child(2){animation-delay:.1s}.voice-waves span:nth-child(3){animation-delay:.2s}.voice-waves span:nth-child(4){animation-delay:.3s}.voice-waves span:nth-child(5){animation-delay:.4s}
@keyframes wave{0%,100%{height:4px}50%{height:18px}}
/* Chat feed */
.cf{padding:16px;display:flex;flex-direction:column;gap:8px;min-height:300px;max-height:420px;overflow-y:auto}
.msg{display:flex;gap:8px;align-items:flex-end}
.bot{flex-direction:row}.usr{flex-direction:row-reverse}
.av2{width:28px;height:28px;border-radius:50%;background:#1D9E75;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.bb{max-width:78%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.5}
.bot .bb{background:#f0f0f0;color:#111;border-radius:4px 16px 16px 16px}
.usr .bb{background:#1D9E75;color:#fff;border-radius:16px 4px 16px 16px}
/* Chips */
.chips-wrap{display:flex;flex-direction:column;gap:6px;padding:0 16px 12px;align-self:flex-start}
.chips-hint{font-size:11px;color:#888;padding:0 4px}
.chips-row{display:flex;flex-wrap:wrap;gap:7px}
.chip{padding:7px 13px;border-radius:20px;border:1px solid #ddd;background:#fff;color:#333;font-size:13px;cursor:pointer;transition:all .12s}
.chip:hover:not(:disabled){background:#f0faf6;border-color:#1D9E75;color:#1D9E75}
.chip.sel{background:#e1f5ee;border-color:#1D9E75;color:#085041}
.chip:disabled{opacity:.5;cursor:default}
.conf{padding:8px 18px;border-radius:20px;border:none;background:#1D9E75;color:#fff;font-size:13px;font-weight:600;cursor:pointer;margin-top:4px}
.conf:disabled{opacity:.5;cursor:default}
/* Receipt */
.rc{margin:8px 16px 16px;background:#f8f8f8;border:1px solid #e5e5e5;border-radius:12px;padding:14px 16px;font-size:13px}
.rt{font-weight:600;font-size:14px;margin-bottom:10px;color:#111}
.rr{display:flex;justify-content:space-between;gap:12px;color:#555;margin-top:4px}
.rT{font-weight:700;color:#111;border-top:1px solid #e0e0e0;margin-top:8px;padding-top:8px}
.rm{color:#777;font-size:12px;padding-left:10px;margin-top:2px}
.ab{margin-top:12px;padding:12px;border-radius:12px;border:none;background:#1D9E75;color:#fff;font-size:14px;font-weight:600;cursor:pointer;width:100%;transition:background .15s}
.ab:hover{background:#0F6E56}
.nav-chips{display:flex;gap:8px;margin-top:10px}
.nav-chip{padding:8px 16px;border-radius:20px;font-size:13px;font-weight:500;text-decoration:none;text-align:center;flex:1}
.nc-sec{border:1px solid #ddd;color:#444;background:#fff}
.nc-pri{background:#1D9E75;color:#fff}
</style>
</head>
<body>
<header class="hdr">
  <a href="<?=$host?>/index.php" class="bk">&larr; Menu</a>
  <div class="logo"><span class="av">CG</span><strong>Cedar Grove</strong></div>
  <a href="<?=$host?>/basket.php" class="bkt">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
    Basket<?php if($basket_count>0):?><span class="bdg"><?=$basket_count?></span><?php endif;?>
  </a>
</header>

<div class="wrap">
  <div class="cw">
    <!-- Header -->
    <div class="ch">
      <div class="ca">CG</div>
      <div class="ch-info">
        <strong><?=htmlspecialchars($item['name'])?></strong>
        <small>Voice &amp; tap ordering &mdash; I'll guide you through</small>
      </div>
    </div>

    <!-- Voice control bar -->
    <div class="voice-bar">
      <button class="mic-btn idle" id="micBtn" title="Click to speak your answer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
          <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
          <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
          <line x1="12" y1="19" x2="12" y2="23"/>
          <line x1="8" y1="23" x2="16" y2="23"/>
        </svg>
      </button>
      <div class="voice-status" id="voiceStatus">Tap the mic to speak, or tap an option below</div>
      <div class="voice-waves" id="voiceWaves" style="display:none">
        <span></span><span></span><span></span><span></span><span></span>
      </div>
    </div>

    <!-- Chat messages -->
    <div class="cf" id="F"></div>

    <!-- Chips area -->
    <div id="chipsArea"></div>
  </div>
</div>

<script>
var ITEM  = <?=json_encode(['id'=>$item['id'],'name'=>$item['name'],'base_price'=>$base_price,'single_size'=>$single_size])?>;
var STEPS = <?=json_encode($steps)?>;
var HOST  = <?=json_encode($host)?>;

// ── State ────────────────────────────────────────
var stepIdx   = 0;
var sizeLabel = ITEM.single_size || '';
var basePrice = ITEM.base_price;
var selections = [];
var multiSel   = [];
var isSpeaking = false;
var isListening = false;

// ── DOM refs ─────────────────────────────────────
var feed       = document.getElementById('F');
var chipsArea  = document.getElementById('chipsArea');
var micBtn     = document.getElementById('micBtn');
var voiceStatus= document.getElementById('voiceStatus');
var voiceWaves = document.getElementById('voiceWaves');

// ── Speech Synthesis ─────────────────────────────
var synth = window.speechSynthesis;
var preferredVoice = null;

function loadVoices() {
    var voices = synth.getVoices();
    // Prefer a female English voice
    preferredVoice = voices.find(function(v) {
        return v.lang.startsWith('en') && /female|zira|samantha|karen|moira|tessa|fiona|victoria/i.test(v.name);
    }) || voices.find(function(v) {
        return v.lang.startsWith('en');
    }) || voices[0];
}
if (synth.onvoiceschanged !== undefined) synth.onvoiceschanged = loadVoices;
loadVoices();

function speak(text, onDone) {
    synth.cancel();
    var utt = new SpeechSynthesisUtterance(text);
    utt.voice = preferredVoice;
    utt.rate  = 0.92;
    utt.pitch = 1.05;
    utt.volume= 1;
    isSpeaking = true;
    micBtn.className = 'mic-btn speaking';
    voiceStatus.innerHTML = '<strong>Speaking...</strong>';
    voiceWaves.style.display = 'flex';
    utt.onend = function() {
        isSpeaking = false;
        voiceWaves.style.display = 'none';
        micBtn.className = 'mic-btn idle';
        voiceStatus.textContent = 'Tap mic to answer, or tap an option below';
        if (onDone) onDone();
    };
    utt.onerror = function() {
        isSpeaking = false;
        voiceWaves.style.display = 'none';
        micBtn.className = 'mic-btn idle';
        if (onDone) onDone();
    };
    synth.speak(utt);
}

// ── Speech Recognition ────────────────────────────
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
var recognition = null;
if (SpeechRecognition) {
    recognition = new SpeechRecognition();
    recognition.continuous  = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US';
}

micBtn.addEventListener('click', function() {
    if (!recognition) {
        voiceStatus.textContent = 'Voice not supported in this browser. Please tap an option.';
        return;
    }
    if (isSpeaking) { synth.cancel(); return; }
    if (isListening) { recognition.stop(); return; }
    startListening();
});

function startListening() {
    if (!recognition || isListening) return;
    isListening = true;
    micBtn.className = 'mic-btn listening';
    voiceStatus.innerHTML = '<strong>Listening...</strong> speak your answer';
    voiceWaves.style.display = 'flex';

    recognition.onresult = function(e) {
        var transcript = '';
        for (var i = e.resultIndex; i < e.results.length; i++) {
            transcript += e.results[i][0].transcript;
        }
        voiceStatus.textContent = 'Heard: "' + transcript + '"';
        if (e.results[e.results.length-1].isFinal) {
            recognition.stop();
            handleVoiceInput(transcript.trim().toLowerCase());
        }
    };
    recognition.onerror = function(e) {
        isListening = false;
        micBtn.className = 'mic-btn idle';
        voiceWaves.style.display = 'none';
        voiceStatus.textContent = 'Could not hear you. Please try again or tap an option.';
    };
    recognition.onend = function() {
        isListening = false;
        micBtn.className = 'mic-btn idle';
        voiceWaves.style.display = 'none';
    };
    recognition.start();
}

function handleVoiceInput(transcript) {
    if (stepIdx >= STEPS.length) return;
    var step  = STEPS[stepIdx];
    var opts  = step.options;
    var multi = step.ui_type === 'checkbox';

    if (multi) {
        // Match multiple options
        var matched = opts.filter(function(o) {
            return transcript.indexOf(o.name.toLowerCase()) !== -1;
        });
        if (matched.length > 0) {
            lockChips();
            handleChoice(matched);
        } else {
            speak('Sorry, I didn\'t catch that. Please say one of: ' +
                opts.map(function(o){return o.name;}).join(', ') +
                '. Or just tap Confirm to skip.');
        }
    } else {
        // Match single option — fuzzy
        var best = null;
        opts.forEach(function(o) {
            if (transcript.indexOf(o.name.toLowerCase()) !== -1) best = o;
        });
        // Also check first word match
        if (!best) {
            opts.forEach(function(o) {
                var first = o.name.split(' ')[0].toLowerCase();
                if (transcript.indexOf(first) !== -1) best = o;
            });
        }
        if (best) {
            lockChips();
            handleChoice([best]);
        } else {
            speak('Sorry, I didn\'t catch that. Please say one of: ' +
                opts.map(function(o){return o.name;}).join(', '));
        }
    }
}

// ── Chat helpers ──────────────────────────────────
function scrollBot() { feed.scrollTop = feed.scrollHeight; }

function addMsg(role, text) {
    var w = document.createElement('div');
    w.className = 'msg ' + (role==='bot'?'bot':'usr');
    if (role==='bot') w.innerHTML = '<div class="av2">CG</div>';
    var b = document.createElement('div');
    b.className = 'bb';
    b.textContent = text;
    w.appendChild(b);
    feed.appendChild(w);
    scrollBot();
}

function lockChips() {
    chipsArea.querySelectorAll('.chip,.conf').forEach(function(b){b.disabled=true;});
}

function showChips(opts, cb, multi) {
    chipsArea.innerHTML = '';
    var wrap = document.createElement('div');
    wrap.className = 'chips-wrap';
    if (multi) {
        var h = document.createElement('p');
        h.className = 'chips-hint';
        h.textContent = 'Select all that apply, then tap Confirm (or speak them)';
        wrap.appendChild(h);
    }
    var row = document.createElement('div');
    row.className = 'chips-row';
    var sel = new Set();

    opts.forEach(function(o) {
        var lbl = o.price_delta > 0 ? o.name + ' (+$' + o.price_delta.toFixed(2) + ')' : o.name;
        var btn = document.createElement('button');
        btn.className = 'chip';
        btn.textContent = lbl;
        if (multi) {
            btn.onclick = function() {
                if (btn.disabled) return;
                btn.classList.toggle('sel');
                if (sel.has(o.id)) sel.delete(o.id); else sel.add(o.id);
                multiSel = opts.filter(function(x){return sel.has(x.id);});
            };
        } else {
            btn.onclick = function() {
                if (btn.disabled) return;
                lockChips();
                cb([o]);
            };
        }
        row.appendChild(btn);
    });
    wrap.appendChild(row);

    if (multi) {
        var conf = document.createElement('button');
        conf.className = 'conf';
        conf.textContent = 'Confirm selection';
        conf.onclick = function() {
            if (conf.disabled) return;
            lockChips();
            cb(multiSel);
            multiSel = [];
        };
        wrap.appendChild(conf);
    }
    chipsArea.appendChild(wrap);
}

// ── Step runner ───────────────────────────────────
function handleChoice(chosen) {
    var step  = STEPS[stepIdx];
    var multi = step.ui_type === 'checkbox';

    if (step.key === '__size__') {
        sizeLabel = chosen[0].name;
        basePrice = chosen[0].price_delta;
        addMsg('user', chosen[0].name);
        stepIdx++;
        setTimeout(run, 400);
        return;
    }

    var display = chosen.length > 0 ? chosen.map(function(o){return o.name;}).join(', ') : 'None';
    addMsg('user', display);
    chosen.filter(function(o){return o.name !== 'None';}).forEach(function(o) {
        selections.push({option_id: o.id, group_label: step.label.replace('?',''), choice: o.name, price_delta: o.price_delta});
    });
    stepIdx++;
    setTimeout(run, 400);
}

function run() {
    chipsArea.innerHTML = '';
    if (stepIdx >= STEPS.length) { showReceipt(); return; }
    var step  = STEPS[stepIdx];
    var multi = step.ui_type === 'checkbox';

    // Build spoken prompt
    var prompt = step.label + ' ';
    var optNames = step.options.map(function(o){return o.name;});
    if (!multi) {
        prompt += 'Your options are: ' + optNames.join(', ') + '.';
    } else {
        prompt += 'You can choose multiple. Options are: ' + optNames.join(', ') + '. Tap Confirm when done.';
    }

    addMsg('bot', step.label);
    speak(prompt, function() {
        // Auto-start listening after speaking for radio steps
        if (!multi) setTimeout(startListening, 300);
    });
    showChips(step.options, handleChoice, multi);
}

// ── Receipt ───────────────────────────────────────
function showReceipt() {
    chipsArea.innerHTML = '';
    var mt  = selections.reduce(function(s,x){return s+(x.price_delta||0);},0);
    var tot = basePrice + mt;

    // Build receipt summary for voice
    var summary = 'Great! Here is your order summary. ' + ITEM.name;
    if (sizeLabel) summary += ', ' + sizeLabel + ' size';
    if (selections.length > 0) {
        summary += ', with ';
        summary += selections.map(function(s){return s.choice;}).join(', ');
    }
    summary += '. Total is $' + tot.toFixed(2) + '. Shall I add this to your basket?';

    addMsg('bot', 'Here is your order summary:');
    speak(summary);

    // Build visual receipt
    var rc = document.createElement('div');
    rc.className = 'rc';
    var h = '<p class="rt">' + esc(ITEM.name) + '</p>';
    if (sizeLabel) {
        h += '<div class="rr"><span>Size</span><span>' + esc(sizeLabel) + '</span></div>';
        h += '<div class="rr"><span>Base price</span><span>$' + basePrice.toFixed(2) + '</span></div>';
    } else {
        h += '<div class="rr"><span>Price</span><span>$' + basePrice.toFixed(2) + '</span></div>';
    }
    selections.forEach(function(s) {
        h += '<div class="rm">' + esc(s.group_label) + ': ' + esc(s.choice);
        if (s.price_delta > 0) h += ' <span style="color:#1D9E75">+$' + s.price_delta.toFixed(2) + '</span>';
        h += '</div>';
    });
    h += '<div class="rr rT"><span>Item total</span><span>$' + tot.toFixed(2) + '</span></div>';

    var btn = document.createElement('button');
    btn.className = 'ab';
    btn.textContent = 'Add to basket';
    btn.onclick = function(){addBasket(tot,btn);};

    rc.innerHTML = h;
    rc.appendChild(btn);
    chipsArea.appendChild(rc);

    // Voice: listen for "yes" to add to basket
    setTimeout(function() {
        if (!recognition) return;
        startListeningForConfirm(tot, btn);
    }, 3000);
}

function startListeningForConfirm(tot, btn) {
    if (!recognition || isListening || isSpeaking) return;
    isListening = true;
    micBtn.className = 'mic-btn listening';
    voiceStatus.innerHTML = '<strong>Listening...</strong> say "yes" to add to basket';
    voiceWaves.style.display = 'flex';
    recognition.onresult = function(e) {
        var t = e.results[e.results.length-1][0].transcript.toLowerCase();
        if (e.results[e.results.length-1].isFinal) {
            recognition.stop();
            if (t.indexOf('yes') !== -1 || t.indexOf('add') !== -1 || t.indexOf('confirm') !== -1) {
                addBasket(tot, btn);
            } else if (t.indexOf('no') !== -1 || t.indexOf('cancel') !== -1) {
                speak('No problem! Tap the back button to return to the menu.');
            } else {
                speak('Please say yes to add to basket, or tap the button.');
            }
        }
    };
    recognition.onerror = function() {
        isListening = false;
        micBtn.className = 'mic-btn idle';
        voiceWaves.style.display = 'none';
    };
    recognition.onend = function() {
        isListening = false;
        micBtn.className = 'mic-btn idle';
        voiceWaves.style.display = 'none';
    };
    recognition.start();
}

async function addBasket(tot, btn) {
    btn.disabled = true;
    btn.textContent = 'Adding…';
    speak('Adding to your basket. Would you like anything else?');
    try {
        var r = await fetch(HOST + '/add_to_basket.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({item_id:ITEM.id,item_name:ITEM.name,size_label:sizeLabel,base_price:basePrice,selections:selections,total:tot})
        });
        var d = await r.json();
        if (d.ok) {
            btn.textContent = '✓ Added to basket!';
            btn.style.background = '#0F6E56';
            var bdg = document.querySelector('.bdg');
            if (!bdg) { bdg=document.createElement('span'); bdg.className='bdg'; document.querySelector('.bkt').appendChild(bdg); }
            bdg.textContent = d.count;
            var nav = document.createElement('div');
            nav.className = 'nav-chips';
            nav.innerHTML = '<a href="'+HOST+'/index.php" class="nav-chip nc-sec">+ Add another item</a>'
                          + '<a href="'+HOST+'/basket.php" class="nav-chip nc-pri">View basket &rarr;</a>';
            chipsArea.appendChild(nav);
        } else {
            btn.textContent = 'Error — try again';
            btn.disabled = false;
        }
    } catch(e) {
        btn.textContent = 'Error — try again';
        btn.disabled = false;
    }
}

function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// ── Boot ──────────────────────────────────────────
speak('Hi! I\'m your order assistant. Let me help you order ' + ITEM.name + '. ' +
      (STEPS.length > 0 ? 'I\'ll ask you a few quick questions.' : ''));
addMsg('bot', 'Hi! Let me help you order ' + ITEM.name + '.');
setTimeout(run, STEPS.length > 0 ? 2500 : 500);
</script>
</body>
</html>
