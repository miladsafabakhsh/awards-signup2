(function ($) {
    $(document).ready(function () {

        $('.js-confirm').click(function (e) {
            e.preventDefault();
            var ask = window.confirm("Are you sure you want to delete this entry?");
            if (ask) {
                window.location.href = $(this).attr('href');
            }
        });

        $('[data-toggle="awards-alert"]').click(function (e) {
            var target = $(this).attr('data-target');
            e.preventDefault();

            $(target).toggleClass('opened');
        });

        $('.awards-alert').click(function (e) {
            if (e.target == this) {
                $('.awards-alert').removeClass('opened');
            }
        });

        $('.entryfeeitem').on('click', 'input', function (e) {
            var entryfeeitem = $(e.target).parents('.entryfeeitem');
            if ($(entryfeeitem).hasClass('disabled')) return;
            $(entryfeeitem).toggleClass('active');

            check_entryfee_total();
        });

        $('body').on('change', 'input[name="entrytype"]', function (e) {
            if ($(this).val() == 'single') {
                $('#file-single').show();
                $('#file-series').hide();
            } else {
                $('#file-single').hide();
                $('#file-series').show();
            }
            check_entryfee_total();
        });

        $('.wp-awards #add-new-person-field').click(function () {
            var len = $('.wp-awards .personitem-area>.personitem').length;

            var html = $('.wp-awards #personitem-template').clone();
            $(html).removeClass('d-none');
            $(html).attr('id', 'personitem' + len);

            $(html).find('.removeperson').attr('data-personid', '#personitem' + len);
            $(html).find('.removeperson').attr('onclick', "deletpersonitem('personitem" + len + "')");
            // title
            $(html).find('[name="person[][title]"]').attr('name', 'person[' + len + '][title]');
            $(html).find('[for="person[][title]"]').attr('for', 'person[' + len + '][title]');
            //First name
            $(html).find('[name="person[][firstname]"]').attr('name', 'person[' + len + '][firstname]');
            $(html).find('[for="person[][firstname]"]').attr('for', 'person[' + len + '][firstname]');
            $(html).find('[id="person[][firstname]"]').attr('id', 'person[' + len + '][firstname]');
            //Last name
            $(html).find('[name="person[][lastname]"]').attr('name', 'person[' + len + '][lastname]');
            $(html).find('[for="person[][lastname]"]').attr('for', 'person[' + len + '][lastname]');
            $(html).find('[id="person[][lastname]"]').attr('id', 'person[' + len + '][lastname]');
            // $(html).search('person[]').attr('data-personid', '#personitem'+len);

            $('.wp-awards .personitem-area').append(html);
        });

        $('.removeperson').click(function () {
            var parentperson = $(this).parents('.personitem');
            $(parentperson).remove();
        });
    });

    function check_entryfee_total() {
        var baseprice;
        if ($('#entry-type-single').is(':checked')) {
            var entry_type = 'single';
        } else if ($('#entry-type-series').is(':checked')) {
            var entry_type = 'series';
        }

        if (entry_type == 'single')
            baseprice = $('#entry-type-single').attr('data-price');
        else if (entry_type == 'series')
            baseprice = $('#entry-type-series').attr('data-price');

        baseprice = parseFloat(baseprice);

        var checked_categories_num = $('input[name="category[]"]:checked').length;
        var cat_add_fee_single = $('#entryfee').attr('data-catfee-single');
        var cat_add_fee_series = $('#entryfee').attr('data-catfee-series');

        cat_add_fee_single = parseInt(cat_add_fee_single);
        cat_add_fee_series = parseInt(cat_add_fee_series);

        if (entry_type == 'single')
            var fee_for_sum = cat_add_fee_single;
        else if (entry_type == 'series')
            var fee_for_sum = cat_add_fee_series;

        var totalprice = 0;
        if (checked_categories_num == 0) {
            return settotalfee(0);
        } else if (checked_categories_num <= 1) {
            return settotalfee(baseprice);
        } else {
            for (i = 1; i <= checked_categories_num; i++) {
                console.log(i);
                totalprice = totalprice + fee_for_sum;
            }
        }

        totalprice = baseprice + totalprice - fee_for_sum;
        return settotalfee(totalprice);
    }

    function settotalfee(fee, op) {
        var element = $('#entryfee').find('.entryfee-num');

        $(element).text(fee);

        return true;
        var value = $(element).text();
        value = parseInt(value);

        if (op == 'minus') {
            var output = value - fee;

        } else {
            var output = value + fee;
        }

        $(element).text(output);
    }
})(jQuery);

