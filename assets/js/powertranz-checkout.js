/**
 * PowerTranz Checkout – Credit Card Form (based on CodePen quinlo/YONMEa)
 * Requires IMask 3.x loaded globally.
 */
(function () {
    'use strict';

    /* ── Wait for DOM (not jQuery dependent) ── */
    function initPowerTranzForm() {
        var wrapper = document.getElementById('wc-powertranz-cc-form');
        if (!wrapper) return;

        var nameInput = document.getElementById('pt-name');
        var cardnumber = document.getElementById('pt-cardnumber');
        var expirationdate = document.getElementById('pt-expirationdate');
        var securitycode = document.getElementById('pt-securitycode');
        var ccicon = document.getElementById('ccicon');
        var ccsingle = document.getElementById('ccsingle');

        /* hidden WooCommerce fields */
        var wcCardNumber = document.getElementById('powertranz-card-number');
        var wcCardExpiry = document.getElementById('powertranz-card-expiry');
        var wcCardCvc = document.getElementById('powertranz-card-cvc');

        if (!cardnumber || typeof IMask === 'undefined') return;

        /* ── IMASK – Card Number ── */
        var cardnumber_mask = new IMask(cardnumber, {
            mask: [
                { mask: '0000 000000 00000', regex: '^3[47]\\d{0,13}', cardtype: 'american express' },
                { mask: '0000 0000 0000 0000', regex: '^(?:6011|65\\d{0,2}|64[4-9]\\d?)\\d{0,12}', cardtype: 'discover' },
                { mask: '0000 000000 0000', regex: '^3(?:0([0-5]|9)|[689]\\d?)\\d{0,11}', cardtype: 'diners' },
                { mask: '0000 0000 0000 0000', regex: '^(5[1-5]\\d{0,2}|22[2-9]\\d{0,1}|2[3-7]\\d{0,2})\\d{0,12}', cardtype: 'mastercard' },
                { mask: '0000 000000 00000', regex: '^(?:2131|1800)\\d{0,11}', cardtype: 'jcb15' },
                { mask: '0000 0000 0000 0000', regex: '^(?:35\\d{0,2})\\d{0,12}', cardtype: 'jcb' },
                { mask: '0000 0000 0000 0000', regex: '^(?:5[0678]\\d{0,2}|6304|67\\d{0,2})\\d{0,12}', cardtype: 'maestro' },
                { mask: '0000 0000 0000 0000', regex: '^4\\d{0,15}', cardtype: 'visa' },
                { mask: '0000 0000 0000 0000', regex: '^62\\d{0,14}', cardtype: 'unionpay' },
                { mask: '0000 0000 0000 0000', cardtype: 'Unknown' }
            ],
            dispatch: function (appended, dynamicMasked) {
                var number = (dynamicMasked.value + appended).replace(/\D/g, '');
                for (var i = 0; i < dynamicMasked.compiledMasks.length; i++) {
                    var re = new RegExp(dynamicMasked.compiledMasks[i].regex);
                    if (number.match(re) != null) {
                        return dynamicMasked.compiledMasks[i];
                    }
                }
            }
        });

        /* ── IMASK – Expiration Date ── */
        var expirationdate_mask = new IMask(expirationdate, {
            mask: 'MM{/}YY',
            groups: {
                YY: new IMask.MaskedPattern.Group.Range([0, 99]),
                MM: new IMask.MaskedPattern.Group.Range([1, 12])
            }
        });

        /* ── IMASK – Security Code ── */
        var securitycode_mask = new IMask(securitycode, {
            mask: '0000'
        });

        /* ──────────────────────────────────────── */
        /* SVG ICON STRINGS (inline)                */
        /* ──────────────────────────────────────── */
        var amex = '<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="amex" fill-rule="nonzero"> <rect id="Rectangle-1" fill="#2557D6" x="0" y="0" width="750" height="471" rx="40"></rect> <path d="M0.002688,221.18508 L36.026849,221.18508 L44.149579,201.67506 L62.334596,201.67506 L70.436042,221.18508 L141.31637,221.18508 L141.31637,206.26909 L147.64322,221.24866 L184.43894,221.24866 L190.76579,206.04654 L190.76579,221.18508 L366.91701,221.18508 L366.83451,189.15941 L370.2427,189.15941 C372.62924,189.24161 373.3263,189.46144 373.3263,193.38516 L373.3263,221.18508 L464.43232,221.18508 L464.43232,213.72973 C471.78082,217.6508 483.21064,221.18508 498.25086,221.18508 L536.57908,221.18508 L544.78163,201.67506 L562.96664,201.67506 L570.98828,221.18508 L644.84844,221.18508 L644.84844,202.65269 L656.0335,221.18508 L715.22061,221.18508 L715.22061,98.67789 L656.64543,98.67789 L656.64543,113.14614 L648.44288,98.67789 L588.33787,98.67789 L588.33787,113.14614 L580.80579,98.67789 L499.61839,98.67789 C486.02818,98.67789 474.08221,100.5669 464.43232,105.83121 L464.43232,98.67789 L408.40596,98.67789 L408.40596,105.83121 C402.26536,100.40529 393.89786,98.67789 384.59383,98.67789 L179.90796,98.67789 L166.17407,130.3194 L152.07037,98.67789 L87.59937,98.67789 L87.59937,113.14614 L80.516924,98.67789 L25.533518,98.67789 L-2.99999999e-06,156.92445 L-2.99999999e-06,221.18508 L0.002597,221.18508 L0.002688,221.18508 Z" id="Path" fill="#FFFFFF"></path> <path d="M749.95644,343.76716 C744.83485,351.22516 734.85504,355.00582 721.34464,355.00582 L680.62723,355.00582 L680.62723,336.1661 L721.17969,336.1661 C725.20248,336.1661 728.01736,335.63887 729.71215,333.99096 C731.18079,332.63183 732.2051,330.65804 732.2051,328.26036 C732.2051,325.70107 731.18079,323.66899 729.62967,322.45028 C728.09984,321.10969 725.87294,320.50033 722.20135,320.50033 C702.40402,319.83005 677.70592,321.10969 677.70592,293.30714 C677.70592,280.56363 685.83131,267.14983 707.95664,267.14983 L749.95379,267.14983 L749.95644,249.66925 L710.93382,249.66925 C699.15812,249.66925 690.60438,252.47759 684.54626,256.84375 L684.54626,249.66925 L626.83044,249.66925 C617.60091,249.66925 606.76706,251.94771 601.64279,256.84375 L601.64279,249.66925 L498.57751,249.66925 L498.57751,256.84375 C490.37496,250.95154 476.53466,249.66925 470.14663,249.66925 L402.16366,249.66925 L402.16366,256.84375 C395.67452,250.58593 381.24357,249.66925 372.44772,249.66925 L296.3633,249.66925 L278.95252,268.43213 L262.64586,249.66925 L148.99149,249.66925 L148.99149,372.26121 L260.50676,372.26121 L278.447,353.20159 L295.34697,372.26121 L364.08554,372.32211 L364.08554,343.48364 L370.84339,343.48364 C379.96384,343.62405 390.72054,343.25845 400.21079,339.17311 L400.21079,372.25852 L456.90762,372.25852 L456.90762,340.30704 L459.64268,340.30704 C463.13336,340.30704 463.47657,340.45011 463.47657,343.92344 L463.47657,372.25587 L635.71144,372.25587 C646.64639,372.25587 658.07621,369.46873 664.40571,364.41107 L664.40571,372.25587 L719.03792,372.25587 C730.40656,372.25587 741.50913,370.66889 749.95644,366.60475 L749.95644,343.76712 L749.95644,343.76716 Z" id="path13" fill="#FFFFFF"></path> </g> </g>';
        var visa = '<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="visa" fill-rule="nonzero"> <rect id="Rectangle-1" fill="#0E4595" x="0" y="0" width="750" height="471" rx="40"></rect> <polygon id="Shape" fill="#FFFFFF" points="278.1975 334.2275 311.5585 138.4655 364.9175 138.4655 331.5335 334.2275"></polygon> <path d="M524.3075,142.6875 C513.7355,138.7215 497.1715,134.4655 476.4845,134.4655 C423.7605,134.4655 386.6205,161.0165 386.3045,199.0695 C386.0075,227.1985 412.8185,242.8905 433.0585,252.2545 C453.8275,261.8495 460.8105,267.9695 460.7115,276.5375 C460.5795,289.6595 444.1255,295.6545 428.7885,295.6545 C407.4315,295.6545 396.0855,292.6875 378.5625,285.3785 L371.6865,282.2665 L364.1975,326.0905 C376.6605,331.5545 399.7065,336.2895 423.6355,336.5345 C479.7245,336.5345 516.1365,310.2875 516.5505,269.6525 C516.7515,247.3835 502.5355,230.4355 471.7515,216.4645 C453.1005,207.4085 441.6785,201.3655 441.7995,192.1955 C441.7995,184.0585 451.4675,175.3575 472.3565,175.3575 C489.8055,175.0865 502.4445,178.8915 512.2925,182.8575 L517.0745,185.1165 L524.3075,142.6875" id="path13" fill="#FFFFFF"></path> <path d="M661.6145,138.4655 L620.3835,138.4655 C607.6105,138.4655 598.0525,141.9515 592.4425,154.6995 L513.1975,334.1025 L569.2285,334.1025 C569.2285,334.1025 578.3905,309.9805 580.4625,304.6845 C586.5855,304.6845 641.0165,304.7685 648.7985,304.7685 C650.3945,311.6215 655.2905,334.1025 655.2905,334.1025 L704.8025,334.1025 L661.6145,138.4655 Z M596.1975,264.8725 C600.6105,253.5935 617.4565,210.1495 617.4565,210.1495 C617.1415,210.6705 621.8365,198.8155 624.5315,191.4655 L628.1385,208.3435 C628.1385,208.3435 638.3555,255.0725 640.4905,264.8715 L596.1975,264.8715 L596.1975,264.8725 Z" id="Path" fill="#FFFFFF"></path> <path d="M232.9025,138.4655 L180.6625,271.9605 L175.0965,244.8315 C165.3715,213.5575 135.0715,179.6755 101.1975,162.7125 L148.9645,333.9155 L205.4195,333.8505 L289.4235,138.4655 L232.9025,138.4655" id="path16" fill="#FFFFFF"></path> <path d="M131.9195,138.4655 L45.8785,138.4655 L45.1975,142.5385 C112.1365,158.7425 156.4295,197.9015 174.8155,244.9525 L156.1065,154.9925 C152.8765,142.5965 143.5085,138.8975 131.9195,138.4655" id="path18" fill="#F2AE14"></path> </g> </g>';
        var mastercard = '<g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="mastercard" fill-rule="nonzero"> <rect id="Rectangle-1" fill="#000000" x="0" y="0" width="750" height="471" rx="40"></rect> <g id="Group" transform="translate(133.000000, 48.000000)"> <g> <rect id="Rectangle-path" fill="#FF5F00" x="169.81" y="31.89" width="143.72" height="234.42"></rect> <path d="M317.05,197.6 C317.05,103.91 205.96,60.74 241.79,32.39 C180.66,-15.67 92.86,-8.69 40.1,48.44 C-12.66,105.56 -12.66,193.64 40.1,250.76 C92.86,307.89 180.66,314.87 241.79,266.81 C205.96,238.46 185.06,195.29 185.05,149.6 Z" fill="#EB001B"></path> <path d="M615.26,197.6 C615.26,92.55 450.76,40.49 399.46,15.54 C348.15,-9.41 287.11,-2.86 242.26,32.39 C278.1,60.73 299,103.91 299,149.6 C299,195.29 278.1,238.47 242.26,266.81 C287.11,302.06 348.15,308.61 399.46,283.66 C450.76,258.71 483.3,206.65 483.26,149.6 Z" fill="#F79E1B"></path> </g> </g> </g> </g>';

        var visa_single = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="750px" height="471px" viewBox="0 0 750 471"><g id="visa"><path fill="#0E4595" d="M278.198,334.228l33.36-195.763h53.358l-33.384,195.763H278.198z"/><path fill="#0E4595" d="M524.307,142.687c-10.57-3.966-27.135-8.222-47.822-8.222c-52.725,0-89.863,26.551-90.18,64.604c-0.297,28.129,26.514,43.821,46.754,53.185c20.77,9.597,27.752,15.716,27.652,24.283c-0.133,13.123-16.586,19.116-31.924,19.116c-21.355,0-32.701-2.967-50.225-10.274l-6.877-3.112l-7.488,43.823c12.463,5.466,35.508,10.199,59.438,10.445c56.09,0,92.502-26.248,92.916-66.884c0.199-22.27-14.016-39.216-44.801-53.188c-18.65-9.056-30.072-15.099-29.951-24.269c0-8.137,9.668-16.838,30.559-16.838c17.447-0.271,30.088,3.534,39.936,7.5l4.781,2.259L524.307,142.687"/><path fill="#0E4595" d="M661.615,138.464h-41.23c-12.773,0-22.332,3.486-27.941,16.234l-79.244,179.402h56.031c0,0,9.16-24.121,11.232-29.418c6.123,0,60.555,0.084,68.336,0.084c1.596,6.854,6.492,29.334,6.492,29.334h49.512L661.615,138.464z M596.198,264.872c4.414-11.279,21.26-54.724,21.26-54.724c-0.314,0.521,4.381-11.334,7.074-18.684l3.607,16.878c0,0,10.217,46.729,12.352,56.527h-44.293V264.872z"/><path fill="#0E4595" d="M232.903,138.464L180.664,271.96l-5.565-27.129c-9.726-31.274-40.025-65.157-73.898-82.12l47.767,171.204l56.455-0.064l84.004-195.386L232.903,138.464"/><path fill="#F2AE14" d="M131.92,138.464H45.879l-0.682,4.073c66.939,16.204,111.232,55.363,129.618,102.415l-18.709-89.96C152.877,142.596,143.509,138.896,131.92,138.464"/></g></svg>';
        var mastercard_single = '<svg xmlns="http://www.w3.org/2000/svg" width="482.51" height="374" viewBox="0 0 482.51 374"> <g> <g> <rect x="169.81" y="31.89" width="143.72" height="234.42" fill="#ff5f00"/> <path d="M317.05,197.6A149.5,149.5,0,0,1,373.79,80.39a149.1,149.1,0,1,0,0,234.42A149.5,149.5,0,0,1,317.05,197.6Z" transform="translate(-132.74 -48.5)" fill="#eb001b"/> <path d="M615.26,197.6a148.95,148.95,0,0,1-241,117.21,149.43,149.43,0,0,0,0-234.42,148.95,148.95,0,0,1,241,117.21Z" transform="translate(-132.74 -48.5)" fill="#f79e1b"/> </g> </g></svg>';
        var amex_single = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="750" height="471" viewBox="0 0 750 471"><g><g><path fill="#2557D6" d="M554.594,130.608l-14.521,35.039h29.121L554.594,130.608z M387.03,152.321c2.738-1.422,4.349-4.515,4.349-8.356c0-3.764-1.693-6.49-4.431-7.771c-2.492-1.42-6.328-1.584-10.006-1.584h-25.978v19.523h25.63C380.7,154.134,384.131,154.074,387.03,152.321z"/></g></g></svg>';

        /* ── Color swap helper ── */
        function swapColor(basecolor) {
            var lightEls = wrapper.querySelectorAll('.lightcolor');
            var darkEls = wrapper.querySelectorAll('.darkcolor');
            lightEls.forEach(function (el) {
                el.setAttribute('class', 'lightcolor ' + basecolor);
            });
            darkEls.forEach(function (el) {
                el.setAttribute('class', 'darkcolor ' + basecolor + 'dark');
            });
        }

        /* ── Card brand detection on input ── */
        cardnumber_mask.on('accept', function () {
            var type = cardnumber_mask.masked.currentMask.cardtype;
            switch (type) {
                case 'american express':
                    ccicon.innerHTML = amex;
                    ccsingle.innerHTML = amex_single;
                    swapColor('green');
                    break;
                case 'visa':
                    ccicon.innerHTML = visa;
                    ccsingle.innerHTML = visa_single;
                    swapColor('lime');
                    break;
                case 'mastercard':
                    ccicon.innerHTML = mastercard;
                    ccsingle.innerHTML = mastercard_single;
                    swapColor('lightblue');
                    break;
                default:
                    ccicon.innerHTML = '';
                    ccsingle.innerHTML = '';
                    swapColor('grey');
                    break;
            }
        });

        /* ── Remove preload class ── */
        var preloadEl = wrapper.querySelector('.preload');
        if (preloadEl) preloadEl.classList.remove('preload');

        /* ── Card flip on click ── */
        var creditcardEl = wrapper.querySelector('.creditcard');
        if (creditcardEl) {
            creditcardEl.addEventListener('click', function () {
                this.classList.toggle('flipped');
            });
        }

        /* ── Live preview: Name ── */
        if (nameInput) {
            nameInput.addEventListener('input', function () {
                var val = nameInput.value.length === 0 ? 'JOHN DOE' : nameInput.value;
                var svgname = document.getElementById('svgname');
                var svgnameback = document.getElementById('svgnameback');
                if (svgname) svgname.textContent = val;
                if (svgnameback) svgnameback.textContent = val;
            });
        }

        /* ── Live preview: Card number ── */
        cardnumber_mask.on('accept', function () {
            var svgnumber = document.getElementById('svgnumber');
            if (svgnumber) {
                svgnumber.textContent = cardnumber_mask.value.length === 0
                    ? '0123 4567 8910 1112'
                    : cardnumber_mask.value;
            }
            /* sync to hidden WC field (digits only) */
            if (wcCardNumber) wcCardNumber.value = cardnumber_mask.unmaskedValue;
        });

        /* ── Live preview: Expiration ── */
        expirationdate_mask.on('accept', function () {
            var svgexpire = document.getElementById('svgexpire');
            if (svgexpire) {
                svgexpire.textContent = expirationdate_mask.value.length === 0
                    ? '01/23'
                    : expirationdate_mask.value;
            }
            /* sync to hidden WC field */
            if (wcCardExpiry) wcCardExpiry.value = expirationdate_mask.value;
        });

        /* ── Live preview: Security Code ── */
        securitycode_mask.on('accept', function () {
            var svgsecurity = document.getElementById('svgsecurity');
            if (svgsecurity) {
                svgsecurity.textContent = securitycode_mask.value.length === 0
                    ? '985'
                    : securitycode_mask.value;
            }
            /* sync to hidden WC field */
            if (wcCardCvc) wcCardCvc.value = securitycode_mask.value;
        });

        /* ── Focus events: flip card ── */
        if (nameInput) nameInput.addEventListener('focus', function () {
            creditcardEl && creditcardEl.classList.remove('flipped');
        });
        cardnumber.addEventListener('focus', function () {
            creditcardEl && creditcardEl.classList.remove('flipped');
        });
        expirationdate.addEventListener('focus', function () {
            creditcardEl && creditcardEl.classList.remove('flipped');
        });
        securitycode.addEventListener('focus', function () {
            creditcardEl && creditcardEl.classList.add('flipped');
        });

        /* ── Sync hidden fields before WooCommerce form submit ── */
        var checkoutForm = document.querySelector('form.checkout, form#order_review');
        if (checkoutForm) {
            checkoutForm.addEventListener('submit', function () {
                if (wcCardNumber) wcCardNumber.value = cardnumber_mask.unmaskedValue;
                if (wcCardExpiry) wcCardExpiry.value = expirationdate_mask.value;
                if (wcCardCvc) wcCardCvc.value = securitycode_mask.value;
            });
        }
    }

    /* Run on DOMContentLoaded */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPowerTranzForm);
    } else {
        initPowerTranzForm();
    }

    /* Also run when WooCommerce updates the checkout via AJAX */
    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('updated_checkout', initPowerTranzForm);
    }
})();
