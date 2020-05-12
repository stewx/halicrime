<!DOCTYPE html>
<html lang="en" ng-app="halicrime">

  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>HaliCrime</title>
    <!-- Bootstrap -->
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" />
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.4.7/angular.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/3.10.1/lodash.min.js"></script>
    <script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=<?php echo $_ENV['GOOGLE_API_KEY']; ?>"></script>
    <link rel="stylesheet" href="css/halicrime.css" />
    <script src="js/halicrime.js"></script>
  </head>

  <body>
    <div id="app" ng-app="halicrime" ng-controller="MapController as mapCtrl">
      <header>
        <h1 id="site-title" class="text-center">halicrime</h1>    
      </header>
      <div class="container-fluid">
        <div class="row">
          <div class="col-xs-12">
            <p>Your subscription has been activated. You will receive emails when crime happens.</p>
            <a class="btn btn-primary" href="/">Back to Crime Map</a>
            
            
          
          </div>
          <!-- end .col-xs-12 -->
        </div>
        <!-- end .row -->
      </div>
      <!-- end .container-fluid -->
    </div>
    <!-- end #app -->
    
    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.8.3/underscore-min.js"></script>
  </body>

</html>
