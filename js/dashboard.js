Array.max=function(array){return Math.max.apply(Math,array);};var chart={canvas:null,ctx:null,graph:null,scale:0,start:0,timer:null,row:0,max:0,width:0,length:0,entries:null,init:function(){chart.canvas=document.getElementById('canvas');if(!chart.canvas||!chart.canvas.getContext||!ab_chart.entries){return;}chart.ctx=chart.canvas.getContext('2d');chart.entries=ab_chart.entries.split(',');chart.length=chart.entries.length;graph=jQuery(chart.canvas);graph.attr('width',graph.parent().width());chart.row=Math.floor(parseInt(graph.parent().width() - 20)/chart.length);chart.width=chart.row * chart.length;chart.max=Array.max(chart.entries);chart.start=new Date().getTime();chart.timer=setInterval(chart.animate,50);chart.ctx.beginPath();chart.ctx.moveTo(0,0);chart.ctx.lineTo(chart.width,0);chart.ctx.strokeStyle='#ccc';chart.ctx.stroke();chart.ctx.textBaseline='top';chart.ctx.font='11px Arial';chart.ctx.textAlign='left';chart.ctx.fillStyle='#999';chart.ctx.fillText(chart.max,chart.width+4,0,20);},draw:function(){chart.ctx.save();chart.ctx.translate(0,chart.canvas.height);chart.ctx.scale(1,-1);for(var i=0;i<chart.length;i++){chart.ctx.fillStyle='#999';chart.ctx.fillRect(i * chart.row,0,chart.row - 1,chart.scale *(chart.entries[i]>0?parseInt(chart.entries[i]/chart.max * 80):1));}chart.ctx.restore();},animate:function(){var diffTime=new Date().getTime() - chart.start;chart.scale=diffTime/150;if(diffTime>=150){chart.scale=1.0;clearInterval(chart.timer);}chart.draw();}};window.onload=chart.init;