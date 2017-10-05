// Put this file in path/to/plugin/amd/src
// You can call it anything you like

define(['jquery', '/report/studentsanalytics/amd/src/chart.min.js'], function($, Chart) {
    return {
        init: function() {

            $(document).ready(function() {

                // data file path
                var flatfiles = '/report/studentsanalytics/flatfiles/';

                /////////////////
                // G E N D E R //
                $.getJSON(flatfiles+"studentAnalyticGender_data.json", function( gData ) {
                    new Chart(document.getElementById("chart-gender").getContext("2d"),
                        {type:'pie',data:gData,options:{title:{display:true, text:'Gender'}}}
                    );
                });
                // G E N D E R //
                /////////////////

                ///////////////////////////
                // N A T I O N A L I T Y //
                $.getJSON(flatfiles+"studentAnalyticNationality_data.json", function( nData ) {
                    new Chart(document.getElementById("chart-nationality").getContext("2d"),
                        {type:'pie', data:nData, options:{title:{display:true, text:'Nationality'}}}
                    );
                });
                // N A T I O N A L I T Y //
                ///////////////////////////

                ////////////
                // A G E //
                $.getJSON(flatfiles+"studentAnalyticAge_data.json", function( aData ) {
                    new Chart(document.getElementById("chart-age").getContext("2d"),
                        {type:'pie', data:aData, options:{title:{display:true, text:'Age Groups'}}}
                    );
                });
                // A G E //
                ///////////

                ////////////////////////////
                // Test Result Per Cohort //
                $.getJSON(flatfiles+"studentAnalytic_CH0001.json", function(gData){
                    new Chart(document.getElementById("chart-CH0001").getContext("2d"),
                        {type:'bar', data:gData, options: {
                            legend: {display: false},
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]}
                        }}
                    );
                });
                $.getJSON(flatfiles+"studentAnalytic_CH0002.json", function(gData){
                    new Chart(document.getElementById("chart-CH0002").getContext("2d"),
                        {type:'bar', data:gData, options: {
                            legend: {display: false},
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]}
                        }}
                    );
                });
                $.getJSON(flatfiles+"studentAnalytic_CH0003.json", function(gData){
                    new Chart(document.getElementById("chart-CH0003").getContext("2d"),
                        {type:'bar', data:gData, options: {
                            legend: {display: false},
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]}
                        }}
                    );
                });
                // Test Result Per Cohort //
                ////////////////////////////

                ////////////////////////////
                // all cohort combined graph
                $.getJSON(flatfiles+"studentAnalytic_CH000A.json", function(gData){
                    new Chart(document.getElementById("chart-CH000A").getContext("2d"),
                        {type:'bar', data:gData, options: {
                            title: {display: true, text: 'Grades for all companies'},
                            scales: {yAxes: [{ticks: {beginAtZero:true}}]}
                        }}
                    );
                });
                // all cohort combined graph
                ////////////////////////////
            });

        } // end: init
    };
});