(function () {
    // Grab the data
    var labels = [],
        data = [];
    jQuery("#ab_chart_data tfoot th").each(function () {
        labels.push(jQuery(this).html());
    });
    jQuery("#ab_chart_data tbody td").each(function () {
        data.push(jQuery(this).html());
    });

    // Draw
    var width = jQuery('#ab_chart').parent().width(),
        height = 140,
        leftgutter = 0,
        bottomgutter = 20,
        topgutter = 20,
        colorhue = .6 || Math.random(),
        color = '#3399CC',
        r = Raphael("ab_chart", width, height),
        txt = {font: 'bold 10px Helvetica, Arial', fill: "#333"},
        txt1 = {font: 'normal 10px Helvetica, Arial', fill: "#ccc"},
        X = (width - leftgutter * 2) / labels.length,
        max = Math.max.apply(Math, data),
        Y = (height - bottomgutter - topgutter) / max;
        r.drawGrid(leftgutter + X * .5, topgutter , width - leftgutter * 2 - X, height - topgutter - bottomgutter, 10, 10, "#eaeaea");

    var Ylabel = r.text(26, 32, max);
        Ylabel.attr(txt1);

    var path = r.path().attr({stroke: color, "stroke-width": 4, "stroke-linejoin": "round"}),
        label = r.set(),
        lx = 0, ly = 0,
        is_label_visible = false,
        leave_timer,
        blanket = r.set();
    label.push(r.text(60, 12, "24× Spam").attr(txt));
    label.push(r.text(60, 27, "15.08.2013").attr(txt).attr({fill: color}));
    label.hide();
    var frame = r.popup(100, 100, label, "right").attr({fill: "#fff", stroke: "#333", "stroke-width": 2, "fill-opacity": .8}).hide();

    var p, bgpp;
    for (var i = 0, ii = labels.length; i < ii; i++) {
        var y = Math.round(height - bottomgutter - Y * data[i]),
            x = Math.round(leftgutter + X * (i + .5));
        if (!i) {
            p = ["M", x, y, "C", x, y];
        }
        if (i && i < ii - 1) {
            var Y0 = Math.round(height - bottomgutter - Y * data[i - 1]),
                X0 = Math.round(leftgutter + X * (i - .5)),
                Y2 = Math.round(height - bottomgutter - Y * data[i + 1]),
                X2 = Math.round(leftgutter + X * (i + 1.5));
            var a = getAnchors(X0, Y0, x, y, X2, Y2);
            p = p.concat([a.x1, a.y1, x, y, a.x2, a.y2]);
        }
        var dot = r.circle(x, y, 3).attr({fill: "#fff", stroke: color, "stroke-width": 2});
        blanket.push(r.rect(leftgutter + X * i, 0, X, height - bottomgutter).attr({stroke: "none", fill: "#fff", opacity: 0}));
        var rect = blanket[blanket.length - 1];
        (function (x, y, data, lbl, dot) {
            var timer, i = 0;
            rect.hover(function () {
                clearTimeout(leave_timer);
                var side = "right";
                if (x + frame.getBBox().width > width) {
                    side = "left";
                }
                var ppp = r.popup(x, y, label, side, 1),
                    anim = Raphael.animation({
                        path: ppp.path,
                        transform: ["t", ppp.dx, ppp.dy]
                    }, 200 * is_label_visible);
                lx = label[0].transform()[0][1] + ppp.dx;
                ly = label[0].transform()[0][2] + ppp.dy;
                frame.show().stop().animate(anim);

                var date = new Date(lbl * 1000),
                    day = ( date.getDate() < 10 ? '0' : '' ) + date.getDate(),
                    month = ( date.getMonth() + 1 < 10 ? '0' : '' ) + (date.getMonth() + 1),
                    year = date.getFullYear();

                label[0].attr({text: data + "× Spam"}).show().stop().animateWith(frame, anim, {transform: ["t", lx, ly]}, 200 * is_label_visible);
                label[1].attr({text: ( day + '.' + month + '.' + year ) }).show().stop().animateWith(frame, anim, {transform: ["t", lx, ly]}, 200 * is_label_visible);
                dot.attr("r", 5);
                is_label_visible = true;
            }, function () {
                dot.attr("r", 3);
                leave_timer = setTimeout(function () {
                    frame.hide();
                    label[0].hide();
                    label[1].hide();
                    is_label_visible = false;
                }, 1);
            });
        })(x, y, data[i], labels[i], dot);
    }
    p = p.concat([x, y, x, y]);
    path.attr({path: p});
    frame.toFront();
    label[0].toFront();
    label[1].toFront();
    blanket.toFront();
})();